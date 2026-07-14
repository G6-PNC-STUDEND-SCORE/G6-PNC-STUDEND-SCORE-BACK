<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScoreDetail;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleSheetsController extends Controller
{
    /**
     * POST /google-sheets/create
     * Create a new Google Sheet with student score data
     */
    public function createSheet(Request $request): JsonResponse
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'term_id' => 'required|exists:terms,id',
            'access_token' => 'required|string',
        ]);

        $subject = Subject::findOrFail($request->subject_id);
        $term = Term::findOrFail($request->term_id);
        $accessToken = $request->access_token;

        $offeringIds = SubjectOffering::where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->pluck('id');

        $enrollments = StudentSubjectEnrollment::with([
            'student.user',
            'student.studentNumberSequence',
            'score.details.assessmentType',
        ])->whereIn('subject_offering_id', $offeringIds)->get();

        $csv = $this->buildCSV($enrollments);

        try {
            $createResponse = Http::withToken($accessToken)
                ->post('https://sheets.googleapis.com/v4/spreadsheets', [
                    'properties' => ['title' => "{$subject->name} - {$term->name}"],
                    'sheets' => [['properties' => ['title' => 'Scores', 'index' => 0]]],
                ]);

            if (!$createResponse->successful()) {
                return response()->json(['message' => 'Failed to create Google Sheet'], 500);
            }

            $spreadsheet = $createResponse->json();
            $spreadsheetId = $spreadsheet['spreadsheetId'];
            $sheetName = $spreadsheet['sheets'][0]['properties']['title'];

            $uploadResponse = Http::withToken($accessToken)
                ->post("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$sheetName}:append", [
                    'valueInputOption' => 'RAW',
                    'insertDataOption' => 'INSERT_ROWS',
                    'range' => "{$sheetName}!A1",
                    'body' => ['values' => $this->csvToArray($csv)],
                ]);

            if (!$uploadResponse->successful()) {
                return response()->json(['message' => 'Failed to upload data'], 500);
            }

            $this->formatSheet($accessToken, $spreadsheetId, $sheetName);

            return response()->json([
                'success' => true,
                'data' => [
                    'spreadsheet_id' => $spreadsheetId,
                    'url' => $spreadsheet['spreadsheetUrl'],
                    'name' => "{$subject->name} - {$term->name}",
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /google-sheets/import
     * Import data from Google Sheet back to system
     */
    public function importSheet(Request $request): JsonResponse
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'term_id' => 'required|exists:terms,id',
            'spreadsheet_id' => 'required|string',
            'access_token' => 'required|string',
        ]);

        $subject = Subject::findOrFail($request->subject_id);
        $term = Term::findOrFail($request->term_id);
        $accessToken = $request->access_token;
        $spreadsheetId = $request->spreadsheet_id;

        try {
            $response = Http::withToken($accessToken)
                ->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/Scores");

            if (!$response->successful()) {
                return response()->json(['message' => 'Failed to fetch sheet'], 500);
            }

            $values = $response->json('values', []);
            if (count($values) < 2) {
                return response()->json(['message' => 'No data found'], 400);
            }

            $rows = array_slice($values, 1);

            DB::beginTransaction();
            try {
                $offeringIds = SubjectOffering::where('subject_id', $subject->id)
                    ->where('term_id', $term->id)
                    ->pluck('id');

                foreach ($rows as $row) {
                    if (count($row) < 2) continue;
                    
                    $studentNumber = $row[1] ?? '';
                    if (!$studentNumber) continue;

                    $enrollment = StudentSubjectEnrollment::whereIn('subject_offering_id', $offeringIds)
                        ->whereHas('student.studentNumberSequence', fn($q) => $q->where('student_number', $studentNumber))
                        ->first();

                    if (!$enrollment) continue;

                    if (!$enrollment->score) {
                        $score = \App\Models\Score::create([
                            'student_subject_enrollment_id' => $enrollment->id,
                        ]);
                    } else {
                        $score = $enrollment->score;
                    }

                    $details = ScoreDetail::with('assessmentType')
                        ->where('score_id', $score->id)
                        ->orderBy('id')
                        ->get();

                    foreach ($details as $index => $detail) {
                        $csvColIndex = 2 + $index;
                        if (isset($row[$csvColIndex]) && $row[$csvColIndex] !== '') {
                            $mark = (float) $row[$csvColIndex];
                            if ($mark >= 0 && $mark <= 100) {
                                $detail->update(['mark' => $mark]);
                            }
                        }
                    }

                    $this->recalculateTotal($score->id);
                }

                DB::commit();
                return response()->json(['success' => true, 'message' => 'Imported successfully']);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    private function buildCSV($enrollments): string
    {
        $columnsMap = collect();
        $enrollments->each(function ($enr) use ($columnsMap) {
            if ($enr->score && $enr->score->details) {
                foreach ($enr->score->details as $d) {
                    $key = $d->label . '_' . ($d->assessmentType?->code ?? 'unknown');
                    if (!$columnsMap->has($key)) {
                        $columnsMap->put($key, [
                            'label' => $d->label,
                            'type' => $d->assessmentType?->code ?? 'unknown',
                        ]);
                    }
                }
            }
        });
        $columns = $columnsMap->values();

        $csv = "Student Name,Student ID";
        foreach ($columns as $col) {
            $csv .= ",{$col['label']} ({$col['type']})";
        }
        $csv .= ",Total,Grade\n";

        foreach ($enrollments as $enr) {
            $name = str_replace(',', ' ', $enr->student->user?->name ?? 'N/A');
            $studentNum = $enr->student->studentNumberSequence?->student_number ?? '';
            $csv .= "{$name},{$studentNum}";

            if ($enr->score) {
                $detailMap = collect();
                foreach ($enr->score->details as $d) {
                    $key = $d->label . '_' . ($d->assessmentType?->code ?? 'unknown');
                    $detailMap->put($key, $d->mark);
                }

                foreach ($columns as $col) {
                    $key = $col['label'] . '_' . $col['type'];
                    $mark = $detailMap->get($key);
                    $csv .= "," . ($mark !== null ? $mark : '');
                }
                $csv .= ",{$enr->score->total},{$enr->score->grade}\n";
            } else {
                foreach ($columns as $col) {
                    $csv .= ",";
                }
                $csv .= ",,\n";
            }
        }

        return $csv;
    }

    private function csvToArray(string $csv): array
    {
        $lines = explode("\n", trim($csv));
        $result = [];
        foreach ($lines as $line) {
            $result[] = str_getcsv($line);
        }
        return $result;
    }

    private function formatSheet(string $accessToken, string $spreadsheetId, string $sheetName): void
    {
        Http::withToken($accessToken)
            ->post("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}:batchUpdate", [
                'requests' => [
                    [
                        'repeatCell' => [
                            'range' => ['sheetId' => 0, 'startRowIndex' => 0, 'endRowIndex' => 1],
                            'cell' => ['userEnteredFormat' => ['textFormat' => ['bold' => true]]],
                            'fields' => 'userEnteredFormat.textFormat.bold',
                        ],
                    ],
                    [
                        'updateSheetProperties' => [
                            'properties' => ['sheetId' => 0, 'gridProperties' => ['frozenRowCount' => 1]],
                            'fields' => 'gridProperties.frozenRowCount',
                        ],
                    ],
                ],
            ]);
    }

    private function recalculateTotal(?int $scoreId): void
    {
        if (!$scoreId) return;
        $score = \App\Models\Score::find($scoreId);
        if (!$score) return;

        $details = ScoreDetail::with('assessmentType')
            ->where('score_id', $scoreId)
            ->whereNotNull('mark')
            ->get();

        if ($details->isEmpty()) {
            $score->update(['total' => null, 'grade' => null]);
            return;
        }

        $total = round($details
            ->groupBy(fn($d) => $d->assessmentType?->code ?? 'unknown')
            ->sum(function ($group) {
                $assessmentType = $group->first()->assessmentType;
                if (!$assessmentType) return 0;
                $average = $this->calculateSimpleAverage($group);
                return (($average ?? 0) * ((float) $assessmentType->weight_percent / 100));
            }), 2);

        $grade = match (true) {
            $total >= 90 => 'A',
            $total >= 80 => 'B+',
            $total >= 75 => 'B',
            $total >= 70 => 'C+',
            $total >= 60 => 'C',
            $total >= 50 => 'D',
            default => 'F',
        };

        $score->update(['total' => $total, 'grade' => $grade]);
    }

    private function calculateSimpleAverage($details): ?float
    {
        $details = $details->filter(fn($d) => $d->mark !== null);
        if ($details->isEmpty()) return null;
        $totalMarks = $details->sum('mark');
        $totalMaxScores = $details->filter(fn($d) => $d->max_score)->sum('max_score');
        if ($totalMaxScores > 0) return ($totalMarks / $totalMaxScores) * 100;
        return $details->avg('mark');
    }
}
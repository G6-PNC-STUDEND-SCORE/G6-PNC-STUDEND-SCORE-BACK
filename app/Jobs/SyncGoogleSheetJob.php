<?php

namespace App\Jobs;

use App\Models\AssessmentType;
use App\Models\GoogleSheetLink;
use App\Models\GoogleSyncLog;
use App\Models\Score;
use App\Models\ScoreDetail;
use App\Models\StudentSubjectEnrollment;
use App\Models\SubjectOffering;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncGoogleSheetJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly int $linkId,
        public readonly string $accessToken,
        public readonly ?int $triggeredBy = null,
    ) {}

    public function handle(): void
    {
        $link = GoogleSheetLink::with(['subject', 'term'])->findOrFail($this->linkId);
        $link->update(['sync_status' => 'syncing']);

        try {
            $result = $this->pullFromSheets($link);

            $link->update([
                'sync_status' => $result['failed'] > 0 ? 'failed' : 'success',
                'last_sync_at' => now(),
                'last_updated_records' => $result['updated'],
                'last_failed_records' => $result['failed'],
            ]);

            GoogleSyncLog::create([
                'google_sheet_link_id' => $link->id,
                'triggered_by' => $this->triggeredBy,
                'direction' => 'pull',
                'status' => $result['failed'] > 0 ? ($result['updated'] > 0 ? 'partial' : 'failed') : 'success',
                'updated_records' => $result['updated'],
                'failed_records' => $result['failed'],
                'changes_summary' => $result['changes'],
            ]);
        } catch (\Throwable $e) {
            Log::error('SyncGoogleSheetJob failed', ['error' => $e->getMessage(), 'link_id' => $this->linkId]);

            $link->update(['sync_status' => 'failed']);

            GoogleSyncLog::create([
                'google_sheet_link_id' => $link->id,
                'triggered_by' => $this->triggeredBy,
                'direction' => 'pull',
                'status' => 'failed',
                'updated_records' => 0,
                'failed_records' => 0,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function pullFromSheets(GoogleSheetLink $link): array
    {
        $response = Http::withToken($this->accessToken)
            ->get("https://sheets.googleapis.com/v4/spreadsheets/{$link->spreadsheet_id}/values/Scores");

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to fetch sheet data: ' . $response->body());
        }

        $values = $response->json('values', []);
        if (count($values) < 2) {
            return ['updated' => 0, 'failed' => 0, 'changes' => []];
        }

        $header = array_map('trim', $values[0]);
        // Columns: Student Name(0), Student ID(1), ...score cols..., Total, Grade
        $scoreCols = array_slice($header, 2, count($header) - 4); // strip last 2 (Total, Grade)

        $offeringIds = SubjectOffering::where('subject_id', $link->subject_id)
            ->where('term_id', $link->term_id)
            ->where('status', 'active')
            ->pluck('id');

        $updated = 0;
        $failed = 0;
        $changes = [];

        DB::beginTransaction();
        try {
            foreach (array_slice($values, 1) as $row) {
                if (count($row) < 2) continue;

                $studentNumber = trim($row[1] ?? '');
                if (!$studentNumber) continue;

                $enrollment = StudentSubjectEnrollment::whereIn('subject_offering_id', $offeringIds)
                    ->whereHas('student.studentNumberSequence', fn($q) => $q->where('student_number', $studentNumber))
                    ->with(['score.details.assessmentType'])
                    ->first();

                if (!$enrollment) { $failed++; continue; }

                if (!$enrollment->score) {
                    $score = Score::create(['student_subject_enrollment_id' => $enrollment->id]);
                    $enrollment->setRelation('score', $score->load('details.assessmentType'));
                } else {
                    $score = $enrollment->score;
                }

                foreach ($scoreCols as $colIdx => $colHeader) {
                    $csvIdx = 2 + $colIdx;
                    $rawValue = $row[$csvIdx] ?? '';
                    if ($rawValue === '' || $rawValue === null) continue;

                    $mark = (float) $rawValue;
                    if ($mark < 0 || $mark > 100) { $failed++; continue; }

                    // Parse "Label (type)" format
                    preg_match('/^(.+?)\s*\((\w+)\)$/', $colHeader, $m);
                    $label = trim($m[1] ?? $colHeader);
                    $type  = strtolower(trim($m[2] ?? 'quiz'));

                    $assessmentType = AssessmentType::firstOrCreate(
                        ['code' => $type],
                        ['name' => ucfirst($type), 'weight_percent' => 0, 'is_active' => true]
                    );

                    $detail = $score->details->first(
                        fn($d) => strtolower($d->label) === strtolower($label)
                            && $d->assessmentType?->code === $type
                    );

                    if (!$detail) {
                        // New column added in Google Sheets → create in system
                        $detail = ScoreDetail::create([
                            'score_id' => $score->id,
                            'assessment_type_id' => $assessmentType->id,
                            'label' => $label,
                            'order_number' => $colIdx,
                            'mark' => $mark,
                        ]);
                        $changes[] = ['student' => $row[0], 'column' => $label, 'old' => null, 'new' => $mark];
                        $updated++;
                    } elseif ((float) $detail->mark !== $mark) {
                        $changes[] = ['student' => $row[0], 'column' => $label, 'old' => $detail->mark, 'new' => $mark];
                        $detail->update(['mark' => $mark]);
                        $updated++;
                    }
                }

                $this->recalculateTotal($score->id);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return ['updated' => $updated, 'failed' => $failed, 'changes' => $changes];
    }

    private function recalculateTotal(int $scoreId): void
    {
        $score = Score::find($scoreId);
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
                $at = $group->first()->assessmentType;
                if (!$at) return 0;
                $marks = $group->filter(fn($d) => $d->mark !== null);
                if ($marks->isEmpty()) return 0;
                $maxScores = $marks->filter(fn($d) => $d->max_score)->sum('max_score');
                $avg = $maxScores > 0
                    ? ($marks->sum('mark') / $maxScores) * 100
                    : $marks->avg('mark');
                return ($avg ?? 0) * ((float) $at->weight_percent / 100);
            }), 2);

        $grade = match (true) {
            $total >= 90 => 'A',
            $total >= 80 => 'B+',
            $total >= 75 => 'B',
            $total >= 70 => 'C+',
            $total >= 60 => 'C',
            $total >= 50 => 'D',
            default      => 'F',
        };

        $score->update(['total' => $total, 'grade' => $grade]);
    }
}

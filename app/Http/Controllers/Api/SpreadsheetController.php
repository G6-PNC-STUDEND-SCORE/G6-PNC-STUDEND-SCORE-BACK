<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentType;
use App\Models\Score;
use App\Models\ScoreDetail;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SpreadsheetController extends Controller
{
    /**
     * GET /spreadsheet/subjects
     * List all subjects grouped by term using the subject_term pivot table.
     * Only subjects assigned to at least one term via the pivot table are shown.
     */
    public function subjects(): JsonResponse
    {
        $subjects = Subject::with(['terms', 'offerings' => function ($q) {
            $q->where('status', 'active')->with(['teacher.user', 'class']);
        }])->get();

        // Only show subjects that are assigned to terms via subject_term pivot
        $result = $subjects->filter(function ($subject) {
            return $subject->terms->isNotEmpty();
        })->values()->map(function ($subject) {
            // Use the subject_term pivot as the source of truth for which
            // terms this subject belongs to (not offerings term_id)
            $terms = $subject->terms->map(function ($term) use ($subject) {
                $offerings = $subject->offerings->where('term_id', $term->id);

                return [
                    'term_id' => $term->id,
                    'term_name' => $term->name,
                    'teachers' => $offerings->pluck('teacher.user.name')->filter()->unique()->values(),
                    'classes' => $offerings->pluck('class.name')->filter()->unique()->values(),
                    'offering_ids' => $offerings->pluck('id'),
                    'enrollment_count' => $offerings->isNotEmpty()
                        ? StudentSubjectEnrollment::whereIn('subject_offering_id', $offerings->pluck('id'))->count()
                        : 0,
                ];
            })->values();

            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->subject_code,
                'terms' => $terms,
            ];
        });

        $terms = Term::orderBy('term_number')->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => [
                'subjects' => $result,
                'terms' => $terms,
            ],
        ]);
    }

    /**
     * GET /spreadsheet/subject/{subject}/term/{term}
     * Returns a spreadsheet for a subject in a given term.
     * Aggregates all offerings of this subject in this term.
     */
    public function bySubjectAndTerm(Subject $subject, Term $term): JsonResponse
    {
        $offeringIds = SubjectOffering::where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->pluck('id');

        $enrollments = StudentSubjectEnrollment::with([
            'student.user',
            'student.studentNumberSequence',
            'score.details.assessmentType',
        ])->whereIn('subject_offering_id', $offeringIds)->get();

        // Collect all unique score-detail columns, deduplicated by label+type
        // This ensures each column (e.g., "Quiz 1") appears only ONCE
        $columnsMap = collect();
        $enrollments->each(function ($enr) use ($columnsMap) {
            if ($enr->score && $enr->score->details) {
                foreach ($enr->score->details as $d) {
                    $key = $d->label . '_' . ($d->assessmentType?->code ?? 'unknown');
                    if (!$columnsMap->has($key)) {
                        $columnsMap->put($key, [
                            'id' => $d->id,  // Use first detail's ID as canonical
                            'label' => $d->label,
                            'type' => $d->assessmentType?->code ?? 'unknown',
                            'order_number' => $d->order_number ?? 0,
                            'max_score' => $d->max_score,
                            'assessment_type_id' => $d->assessment_type_id,
                        ]);
                    }
                }
            }
        });
        $columns = $columnsMap->values()->sortBy('order_number')->values();

        // Build rows - map each student's marks to the canonical column IDs
        $rows = $enrollments->map(function ($enr) use ($columns) {
            $detailMarks = [];
            $detailIdMap = [];
            
            // Create a map of label_type -> [mark, detail_id] for this student
            $studentMarkMap = collect();
            if ($enr->score && $enr->score->details) {
                foreach ($enr->score->details as $d) {
                    $key = $d->label . '_' . ($d->assessmentType?->code ?? 'unknown');
                    $studentMarkMap->put($key, [
                        'mark' => $d->mark !== null ? (float) $d->mark : null,
                        'detail_id' => $d->id
                    ]);
                }
            }
            
            // Map canonical column IDs to this student's marks AND their actual detail IDs
            foreach ($columns as $col) {
                $key = $col['label'] . '_' . $col['type'];
                $studentData = $studentMarkMap->get($key);
                $detailMarks[$col['id']] = $studentData ? $studentData['mark'] : null;
                $detailIdMap[$col['id']] = $studentData ? $studentData['detail_id'] : null;
            }

            return [
                'enrollment_id' => $enr->id,
                'score_id' => $enr->score?->id,
                'student_id' => $enr->student->id,
                'student_name' => $enr->student->user?->name ?? 'N/A',
                'student_number' => $enr->student->studentNumberSequence?->student_number ?? '',
                'offering_id' => $enr->subject_offering_id,
                'total' => $enr->score?->total !== null ? (float) $enr->score->total : null,
                'grade' => $enr->score?->grade,
                'details' => $detailMarks,
                'detail_ids' => $detailIdMap, // Maps canonical column ID -> actual detail ID for this student
            ];
        });

        $offerings = SubjectOffering::whereIn('id', $offeringIds)
            ->with(['teacher.user', 'class'])
            ->get();

        $assessmentTypes = AssessmentType::where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'weight_percent']);

        return response()->json([
            'success' => true,
            'data' => [
                'subject' => $subject,
                'term' => $term,
                'offerings' => $offerings->map(fn($o) => [
                    'teacher_name' => $o->teacher?->user?->name ?? 'N/A',
                    'class_name' => $o->class?->name ?? 'N/A',
                ]),
                'columns' => $columns,
                'rows' => $rows,
                'assessment_types' => $assessmentTypes,
            ],
        ]);
    }

    /**
     * PUT /spreadsheet/subject/{subject}/term/{term}/details/{detail}
     * Inline update of a score detail mark.
     */
    public function updateDetail(Request $request, Subject $subject, Term $term, ScoreDetail $detail): JsonResponse
    {
        $request->validate([
            'mark' => 'nullable|numeric|min:0|max:100',
        ]);

        $detail->update(['mark' => $request->mark]);
        $this->recalculateTotal($detail->score_id);

        return response()->json(['success' => true, 'data' => $detail->fresh()]);
    }

    /**
     * POST /spreadsheet/subject/{subject}/term/{term}/details
     * Add a new score-detail column for all enrollments of this subject+term.
     */
    public function addDetail(Request $request, Subject $subject, Term $term): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:quiz,assignment,midterm,final,project',
            'label' => 'required|string|max:50',
            'max_score' => 'nullable|integer|min:1',
            'order_number' => 'nullable|integer',
        ]);

        $assessmentType = AssessmentType::firstOrCreate(
            ['code' => $request->type],
            [
                'name' => ucfirst($request->type),
                'weight_percent' => match ($request->type) {
                    'quiz' => 10,
                    'assignment' => 20,
                    'project' => 20,
                    'midterm' => 20,
                    'final' => 30,
                    default => 0,
                },
                'is_active' => true,
            ]
        );

        $offeringIds = SubjectOffering::where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->pluck('id');

        $enrollments = StudentSubjectEnrollment::with('score')
            ->whereIn('subject_offering_id', $offeringIds)
            ->get();

        DB::beginTransaction();
        try {
            foreach ($enrollments as $enr) {
                if (!$enr->score) {
                    $score = Score::create([
                        'student_subject_enrollment_id' => $enr->id,
                    ]);
                } else {
                    $score = $enr->score;
                }

                $detail = ScoreDetail::create([
                    'score_id' => $score->id,
                    'assessment_type_id' => $assessmentType->id,
                    'label' => $request->label,
                    'max_score' => $request->max_score,
                    'order_number' => $request->order_number ?? 0,
                    'mark' => null,
                ]);
                $this->recalculateTotal($score->id);
            }
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Column added.'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /spreadsheet/subject/{subject}/term/{term}/details/{detail}
     */
    public function deleteDetail(Subject $subject, Term $term, ScoreDetail $detail): JsonResponse
    {
        $scoreId = $detail->score_id;
        $detail->delete();
        if ($scoreId) $this->recalculateTotal($scoreId);
        return response()->json(['success' => true, 'message' => 'Detail deleted.']);
    }

    /**
     * PATCH /spreadsheet/subject/{subject}/term/{term}/details/{detail}/rename
     */
    public function renameDetail(Request $request, Subject $subject, Term $term, ScoreDetail $detail): JsonResponse
    {
        $request->validate(['label' => 'required|string|max:50']);
        $detail->update(['label' => $request->label]);
        return response()->json(['success' => true, 'data' => $detail->fresh()]);
    }

    /**
     * POST /spreadsheet/subject/{subject}/term/{term}/reorder
     */
    public function reorderColumns(Request $request, Subject $subject, Term $term): JsonResponse
    {
        $request->validate([
            'columns' => 'required|array',
            'columns.*.id' => 'required|exists:score_details,id',
            'columns.*.order_number' => 'required|integer',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->columns as $col) {
                ScoreDetail::where('id', $col['id'])->update(['order_number' => $col['order_number']]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
        return response()->json(['success' => true]);
    }

    /**
     * PUT /spreadsheet/weights
     */
    public function updateWeights(Request $request): JsonResponse
    {
        $request->validate([
            'weights' => 'required|array',
            'weights.*.id' => 'required|exists:assessment_types,id',
            'weights.*.weight_percent' => 'required|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->weights as $w) {
                AssessmentType::where('id', $w['id'])->update(['weight_percent' => $w['weight_percent']]);
            }
            Score::whereNotNull('total')->chunk(100, function ($scores) {
                foreach ($scores as $score) {
                    $this->recalculateTotal($score->id);
                }
            });
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Weights updated.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /spreadsheet/subject/{subject}/term/{term}/sync-google
     * Exports data ready for Google Sheets integration.
     * This generates a CSV-compatible blob URL for direct Google Sheets opening.
     */
    public function syncToGoogleSheets(Subject $subject, Term $term): JsonResponse
    {
        $offeringIds = SubjectOffering::where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->pluck('id');

        $enrollments = StudentSubjectEnrollment::with([
            'student.user',
            'student.studentNumberSequence',
            'score.details.assessmentType',
        ])->whereIn('subject_offering_id', $offeringIds)->get();

        // Generate CSV content with DEDUPLICATED columns (same as bySubjectAndTerm)
        $columnsMap = collect();
        $enrollments->each(function ($enr) use ($columnsMap) {
            if ($enr->score && $enr->score->details) {
                foreach ($enr->score->details as $d) {
                    $key = $d->label . '_' . ($d->assessmentType?->code ?? 'unknown');
                    if (!$columnsMap->has($key)) {
                        $columnsMap->put($key, [
                            'id' => $d->id,
                            'label' => $d->label,
                            'type' => $d->assessmentType?->code ?? 'unknown',
                        ]);
                    }
                }
            }
        });
        $columns = $columnsMap->values();

        // Build CSV
        $colLabels = $columns->pluck('label', 'id');
        $colTypes = $columns->pluck('type', 'id');
        $colIds = $columns->pluck('id');

        $csv = "Student Name,Student ID";
        foreach ($colLabels as $id => $label) {
            $type = $colTypes[$id] ?? '';
            $csv .= ",{$label} ({$type})";
        }
        $csv .= ",Total,Grade,Remarks\n";

        foreach ($enrollments as $enr) {
            $name = str_replace(',', ' ', $enr->student->user?->name ?? 'N/A');
            $studentNum = $enr->student->studentNumberSequence?->student_number ?? '';
            $csv .= "{$name},{$studentNum}";
            if ($enr->score) {
                $detailMap = [];
                foreach ($enr->score->details as $d) {
                    $detailMap[$d->id] = $d->mark;
                }
                foreach ($colIds as $id) {
                    $mark = $detailMap[$id] ?? null;
                    $csv .= "," . ($mark !== null ? $mark : '');
                }
                $csv .= ",{$enr->score->total},{$enr->score->grade}," . ($enr->score->remarks ?? '');
            } else {
                foreach ($colIds as $id) {
                    $csv .= ",";
                }
                $csv .= ",,,";
            }
            $csv .= "\n";
        }

        // Encode the CSV for Google Sheets import
        $csvEncoded = base64_encode($csv);
        
        // Use Google Sheets import URL with CSV data
        // This will open Google Sheets and import the CSV data directly
        $googleSheetsUrl = "https://docs.google.com/spreadsheets/create?csv=" . $csvEncoded;

        return response()->json([
            'success' => true,
            'data' => [
                'csv_content' => $csv,
                'google_sheets_url' => $googleSheetsUrl,
                'download_url' => "data:text/csv;base64,{$csvEncoded}",
            ],
        ]);
    }

    /**
     * POST /spreadsheet/subject/{subject}/term/{term}/import-google
     * Import data back from Google Sheets (accepts CSV content).
     */
    public function importFromGoogleSheets(Request $request, Subject $subject, Term $term): JsonResponse
    {
        $request->validate([
            'csv_content' => 'required|string',
        ]);

        $lines = explode("\n", $request->csv_content);
        if (count($lines) < 2) {
            return response()->json(['message' => 'CSV must have at least a header and one row.'], 400);
        }

        $header = str_getcsv($lines[0]);
        // Header format: Student Name, Student ID, col1 (type), col2 (type), ..., Total, Grade, Remarks

        DB::beginTransaction();
        try {
            for ($i = 1; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if (empty($line)) continue;
                $data = str_getcsv($line);
                if (count($data) < 2) continue;

                $studentName = $data[0];
                $studentNumber = $data[1] ?? '';

                // Find the enrollment by student number
                $enrollment = StudentSubjectEnrollment::whereIn('subject_offering_id', $offeringIds = SubjectOffering::where('subject_id', $subject->id)->where('term_id', $term->id)->pluck('id'))
                    ->whereHas('student.studentNumberSequence', fn($q) => $q->where('student_number', $studentNumber))
                    ->first();

                if (!$enrollment) continue;

                // Ensure score exists
                if (!$enrollment->score) {
                    $score = Score::create(['student_subject_enrollment_id' => $enrollment->id]);
                } else {
                    $score = $enrollment->score;
                }

                // Parse marks from columns (skip first 2: name, id; skip last 3: total, grade, remarks)
                $colIndex = 0;
                $details = ScoreDetail::with('assessmentType')
                    ->where('score_id', $score->id)
                    ->orderBy('id')
                    ->get();

                foreach ($details as $detail) {
                    $csvColIndex = 2 + $colIndex;
                    if (isset($data[$csvColIndex]) && $data[$csvColIndex] !== '') {
                        $mark = (float) $data[$csvColIndex];
                        if ($mark >= 0 && $mark <= 100) {
                            $detail->update(['mark' => $mark]);
                        }
                    }
                    $colIndex++;
                }

                $this->recalculateTotal($score->id);
            }
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Data imported successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function recalculateTotal(?int $scoreId): void
    {
        if (!$scoreId) return;
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
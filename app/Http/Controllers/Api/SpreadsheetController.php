<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentType;
use App\Models\Score;
use App\Models\ScoreDetail;
use App\Models\Student;
use App\Models\StudentNumberSequence;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\Term;
use App\Models\User;
use App\Services\ScoreCalculationService;
use App\Support\AssessmentTypeDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpreadsheetController extends Controller
{
    public function __construct(
        protected ScoreCalculationService $scoreCalculator
    ) {}

    /**
     * GET /spreadsheet/subjects
     * List all subjects that have active offerings grouped by term.
     */
    public function subjects(): JsonResponse
    {
        $subjects = Subject::whereHas('offerings', function ($q) {
            $q->where('status', 'active');
        })->with(['offerings' => function ($q) {
            $q->where('status', 'active')->with(['teacher.user', 'class', 'term']);
        }])->get();

        // Group offerings by term for each subject
        $result = $subjects->map(function ($subject) {
            $terms = $subject->offerings->groupBy(fn($o) => $o->term_id)->map(function ($offerings, $termId) {
                $first = $offerings->first();
                return [
                    'term_id' => (int) $termId,
                    'term_name' => $first->term?->name ?? 'N/A',
                    'teachers' => $offerings->pluck('teacher.user.name')->filter()->unique()->values(),
                    'classes' => $offerings->pluck('class.name')->filter()->unique()->values(),
                    'offering_ids' => $offerings->pluck('id'),
                    'enrollment_count' => StudentSubjectEnrollment::whereIn('subject_offering_id', $offerings->pluck('id'))->count(),
                ];
            })->values();

            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->subject_code,
                'terms' => $terms,
            ];
        });

        $terms = Term::orderBy('id', 'desc')->get(['id', 'name']);

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
                            'order_number' => $d->order_number ?? 0,
                            'max_score' => $d->max_score,
                            'assessment_type_id' => $d->assessment_type_id,
                        ]);
                    }
                }
            }
        });
        $columns = $columnsMap->values()->sortBy('order_number')->values();

        // Ensure every enrollment has a Score + ScoreDetail for each column
        // Fixes the bug where students enrolled after columns were created
        // would have no detail_id, causing score updates to overwrite the wrong student
        $enrollments->each(function ($enr) use ($columns) {
            if (!$enr->score) {
                $score = Score::create(['student_subject_enrollment_id' => $enr->id]);
                $enr->setRelation('score', $score);
            }

            $existingKeys = collect();
            if ($enr->score->details) {
                foreach ($enr->score->details as $d) {
                    $key = $d->label . '_' . ($d->assessmentType?->code ?? 'unknown');
                    $existingKeys->push($key);
                }
            }

            foreach ($columns as $col) {
                $key = $col['label'] . '_' . $col['type'];
                if (!$existingKeys->contains($key)) {
                    ScoreDetail::create([
                        'score_id' => $enr->score->id,
                        'assessment_type_id' => $col['assessment_type_id'],
                        'label' => $col['label'],
                        'max_score' => $col['max_score'],
                        'order_number' => $col['order_number'],
                        'mark' => null,
                    ]);
                }
            }

            // Reload details so rows below have accurate data
            $enr->score->load('details.assessmentType');
        });

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
                'student_id' => $enr->student?->id,
                'student_name' => $enr->student?->user?->name ?? 'N/A',
                'student_number' => $enr->student?->studentNumberSequence?->student_number ?? '',
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
        $this->scoreCalculator->recalculateById($detail->score_id);

        return response()->json(['success' => true, 'data' => $detail->fresh()]);
    }

    /**
     * POST /spreadsheet/subject/{subject}/term/{term}/details
     * Add a new score-detail column for all enrollments of this subject+term.
     */
    public function addDetail(Request $request, Subject $subject, Term $term): JsonResponse
    {
        $allowedTypes = implode(',', AssessmentTypeDefaults::codes());

        $request->validate([
            'type' => "required|in:{$allowedTypes}",
            'label' => 'required|string|max:50',
            'max_score' => 'nullable|integer|min:1',
            'order_number' => 'nullable|integer',
        ]);

        $assessmentType = AssessmentType::firstOrCreate(
            ['code' => $request->type],
            [
                'name' => ucfirst($request->type),
                'weight_percent' => AssessmentTypeDefaults::weightFor($request->type),
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
                $this->scoreCalculator->recalculate($score);
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
        $this->scoreCalculator->recalculateById($scoreId);
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
     * PATCH /spreadsheet/subject/{subject}/term/{term}/details/change-type
     * Reassign all matching columns from one assessment type to another.
     */
    public function changeColumnType(Request $request, Subject $subject, Term $term): JsonResponse
    {
        $allowedTypes = implode(',', AssessmentTypeDefaults::codes());

        $request->validate([
            'label' => 'required|string|max:50',
            'old_type' => "required|string|in:{$allowedTypes}",
            'new_type' => "required|string|in:{$allowedTypes}",
        ]);

        if ($request->old_type === $request->new_type) {
            return response()->json([
                'success' => true,
                'data' => ['updated_count' => 0],
                'message' => 'Column type updated successfully',
            ]);
        }

        $offeringIds = SubjectOffering::where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->pluck('id');

        if ($offeringIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => ['updated_count' => 0],
                'message' => 'Column type updated successfully',
            ]);
        }

        $oldType = AssessmentType::where('code', $request->old_type)->first();
        $newType = AssessmentType::firstOrCreate(
            ['code' => $request->new_type],
            [
                'name' => ucfirst($request->new_type),
                'weight_percent' => AssessmentTypeDefaults::weightFor($request->new_type),
                'is_active' => true,
            ]
        );

        if (!$oldType) {
            return response()->json([
                'success' => true,
                'data' => ['updated_count' => 0],
                'message' => 'Column type updated successfully',
            ]);
        }

        $detailQuery = ScoreDetail::query()
            ->where('label', $request->label)
            ->where('assessment_type_id', $oldType->id)
            ->whereHas('score.enrollment', function ($enrollmentQuery) use ($offeringIds) {
                $enrollmentQuery->whereIn('subject_offering_id', $offeringIds);
            });

        $scoreIds = (clone $detailQuery)
            ->distinct()
            ->pluck('score_id');

        $updatedCount = $detailQuery->update([
            'assessment_type_id' => $newType->id,
        ]);

        foreach ($scoreIds as $scoreId) {
            $this->scoreCalculator->recalculateById((int) $scoreId);
        }

        return response()->json([
            'success' => true,
            'data' => ['updated_count' => $updatedCount],
            'message' => 'Column type updated successfully',
        ]);
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
                    $this->scoreCalculator->recalculate($score);
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
     * GET /spreadsheet/student-numbers
     * Returns all student numbers for the autocomplete dropdown.
     */
    public function studentNumbers(): JsonResponse
    {
        $numbers = StudentNumberSequence::pluck('student_number');
        return response()->json(['success' => true, 'data' => $numbers]);
    }

    /**
     * POST /spreadsheet/subject/{subject}/term/{term}/enrollments
     * Add a new student enrollment to this subject+term.
     */
    public function addEnrollment(Request $request, Subject $subject, Term $term): JsonResponse
    {
        $request->validate([
            'student_id' => 'nullable|integer|exists:students,id',
        ]);

        $offering = SubjectOffering::where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->first();

        if (!$offering) {
            return response()->json(['message' => 'No active offering found for this subject and term.'], 404);
        }

        $enrollment = StudentSubjectEnrollment::create([
            'student_id' => $request->student_id,
            'subject_offering_id' => $offering->id,
            'status' => 'enrolled',
        ]);

        Score::create(['student_subject_enrollment_id' => $enrollment->id]);

        return response()->json(['success' => true, 'data' => $enrollment], 201);
    }

    /**
     * PUT /spreadsheet/subject/{subject}/term/{term}/enrollments/{enrollment}
     * Update student name and/or number on an enrollment.
     */
    public function updateEnrollment(Request $request, Subject $subject, Term $term, StudentSubjectEnrollment $enrollment): JsonResponse
    {
        $request->validate([
            'student_name' => 'nullable|string|max:100',
            'student_number' => 'nullable|string|max:50',
        ]);

        $student = $enrollment->student;

        if ($request->filled('student_name')) {
            if ($student) {
                $student->user->update(['name' => $request->student_name]);
            } else {
                // Create a new user + student for this enrollment
                $email = 'student_' . uniqid() . '@example.com';
                $studentRoleId = \App\Models\RBAC\Role::where('slug', 'student')->value('id');
                $user = User::create([
                    'name' => $request->student_name,
                    'email' => $email,
                    'password' => bcrypt('password'),
                    'role_id' => $studentRoleId,
                    'status' => 'active',
                ]);
                $student = Student::create([
                    'user_id' => $user->id,
                ]);
                $enrollment->update(['student_id' => $student->id]);
            }
        }

        if ($request->filled('student_number')) {
            if ($student && $student->studentNumberSequence) {
                $student->studentNumberSequence->update(['student_number' => $request->student_number]);
            } elseif ($student) {
                $seq = StudentNumberSequence::create([
                    'student_number' => $request->student_number,
                    'intake_year' => date('Y'),
                ]);
                $student->update(['student_number_sequence_id' => $seq->id]);
            }
        }

        // Reload to get fresh data
        $enrollment->load('student.user', 'student.studentNumberSequence');

        return response()->json([
            'success' => true,
            'data' => [
                'student_name' => $enrollment->student?->user?->name ?? $request->student_name ?? '',
                'student_number' => $enrollment->student?->studentNumberSequence?->student_number ?? $request->student_number ?? '',
            ],
        ]);
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
            $name = str_replace(',', ' ', $enr->student?->user?->name ?? 'N/A');
            $studentNum = $enr->student?->studentNumberSequence?->student_number ?? '';
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

                $this->scoreCalculator->recalculate($score);
            }
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Data imported successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

}

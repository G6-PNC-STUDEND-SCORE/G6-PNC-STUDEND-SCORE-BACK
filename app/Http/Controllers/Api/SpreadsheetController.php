<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentType;
use App\Models\GradeBoundary;
use App\Models\RBAC\Role;
use App\Models\Score;
use App\Models\ScoreDetail;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\Term;
use App\Models\User;
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
        $subjects = Subject::with(['terms.academicYear', 'offerings' => function ($q) {
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
                    'academic_year_id' => $term->academic_year_id,
                    'academic_year' => $term->academicYear?->year ?? $term->academicYear?->name ?? null,
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
            'subjectOffering.class',
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
                'student_name' => $enr->student?->user?->name ?? '',
                'student_number' => $enr->student?->student_id_number ?? '',
                'class_name' => $enr->subjectOffering?->class?->name ?? '',
                'offering_id' => $enr->subject_offering_id,
                'total' => $enr->score?->total !== null ? (float) $enr->score->total : null,
                'grade' => $enr->score?->grade,
                'details' => $detailMarks,
                'detail_ids' => $detailIdMap,
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

        $detail->update(['score' => $request->mark]);
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
     * Deletes ALL ScoreDetail records with the same label + assessment_type
     * across all enrollments for this subject+term (not just one record).
     */
    public function deleteDetail(Subject $subject, Term $term, ScoreDetail $detail): JsonResponse
    {
        $label = $detail->label;
        $assessmentTypeId = $detail->assessment_type_id;

        // Get all offering IDs for this subject+term
        $offeringIds = SubjectOffering::where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->pluck('id');

        // Get all score IDs for these offerings
        $scoreIds = Score::whereIn('student_subject_enrollment_id', function ($q) use ($offeringIds) {
            $q->select('id')->from('student_subject_enrollments')
              ->whereIn('subject_offering_id', $offeringIds);
        })->pluck('id');

        // Delete ALL ScoreDetail records with the same label + assessment_type for these scores
        $deletedCount = ScoreDetail::whereIn('score_id', $scoreIds)
            ->where('label', $label)
            ->where('assessment_type_id', $assessmentTypeId)
            ->delete();

        // Recalculate totals for all affected scores
        foreach ($scoreIds as $scoreId) {
            $this->recalculateTotal($scoreId);
        }

        return response()->json([
            'success' => true,
            'message' => "Column deleted ({$deletedCount} records removed).",
        ]);
    }

    /**
     * PATCH /spreadsheet/subject/{subject}/term/{term}/details/change-type
     * Change the assessment type of all columns with a given label.
     */
    public function changeColumnType(Request $request, Subject $subject, Term $term): JsonResponse
    {
        $request->validate([
            'label' => 'required|string|max:50',
            'old_type' => 'required|string|max:50',
            'new_type' => 'required|string|max:50',
        ]);

        $offeringIds = SubjectOffering::where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->pluck('id');

        $scoreIds = Score::whereIn('student_subject_enrollment_id', function ($q) use ($offeringIds) {
            $q->select('id')->from('student_subject_enrollments')
              ->whereIn('subject_offering_id', $offeringIds);
        })->pluck('id');

        $newAssessmentType = AssessmentType::firstOrCreate(
            ['code' => $request->new_type],
            [
                'name' => ucfirst($request->new_type),
                'weight_percent' => match ($request->new_type) {
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

        DB::beginTransaction();
        try {
            $updatedCount = ScoreDetail::whereIn('score_id', $scoreIds)
                ->where('label', $request->label)
                ->whereHas('assessmentType', fn($q) => $q->where('code', $request->old_type))
                ->update(['assessment_type_id' => $newAssessmentType->id]);

            // Recalculate totals for affected scores
            $affectedScoreIds = ScoreDetail::whereIn('score_id', $scoreIds)
                ->where('label', $request->label)
                ->pluck('score_id')
                ->unique();

            foreach ($affectedScoreIds as $sid) {
                $this->recalculateTotal($sid);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'data' => ['updated_count' => $updatedCount],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
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
                ScoreDetail::where('id', $col['id'])->update(['sequence_number' => $col['order_number']]);
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
     * GET /spreadsheet/student-numbers
     * Returns all student numbers for the autocomplete dropdown.
     */
    public function studentNumbers(): JsonResponse
    {
        $numbers = Student::whereNotNull('student_id_number')->pluck('student_id_number');
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

        try {
            return DB::transaction(function () use ($request, $offering) {
                $student = null;

                if ($request->filled('student_id')) {
                    $student = Student::find($request->student_id);
                } else {
                    $studentRoleId = Role::where('slug', 'student')->value('id');
                    $user = User::create([
                        'name' => '',
                        'email' => 'pending_student_' . uniqid() . '@example.com',
                        'password' => bcrypt('password'),
                        'role_id' => $studentRoleId,
                        'status' => 'active',
                    ]);

                    // Generate a student ID number (must be inside transaction for lockForUpdate)
                    $intakeYear = now()->year;
                    $studentNumber = app(\App\Services\StudentNumberService::class)->createSequence($intakeYear);

                    $student = Student::create([
                        'user_id' => $user->id,
                        'student_id_number' => $studentNumber,
                    ]);
                }

                $studentClassHistory = $this->getOrCreateStudentClassHistory($offering, $student);

                $enrollment = StudentSubjectEnrollment::create([
                    'student_id' => $student->id,
                    'student_class_history_id' => $studentClassHistory->id,
                    'subject_offering_id' => $offering->id,
                    'status' => 'enrolled',
                ]);

                Score::create(['student_subject_enrollment_id' => $enrollment->id]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $enrollment->id,
                        'student_id' => $student->id,
                        'student_number' => $student->student_id_number ?? '',
                    ],
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /spreadsheet/subject/{subject}/term/{term}/enrollments/{enrollment}
     * Delete a student enrollment row and its associated score.
     */
    public function deleteEnrollment(Subject $subject, Term $term, StudentSubjectEnrollment $enrollment): JsonResponse
    {
        DB::beginTransaction();
        try {
            // Delete score details first
            if ($enrollment->score) {
                $enrollment->score->details()->delete();
                $enrollment->score->delete();
            }

            // Delete the enrollment itself
            $enrollment->delete();

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Enrollment deleted.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function getOrCreateStudentClassHistory(SubjectOffering $offering, Student $student): StudentClassHistory
    {
        $generationId = $offering->generation_id ?? $student->generation_id;

        $history = StudentClassHistory::where('student_id', $student->id)
            ->where('class_id', $offering->class_id)
            ->where('generation_id', $generationId)
            ->where('status', 'active')
            ->first();

        if ($history) {
            return $history;
        }

        StudentClassHistory::where('student_id', $student->id)
            ->where('status', 'active')
            ->update(['status' => 'transferred', 'end_date' => now()]);

        return StudentClassHistory::create([
            'student_id' => $student->id,
            'class_id' => $offering->class_id,
            'generation_id' => $generationId,
            'start_date' => now(),
            'status' => 'active',
        ]);
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

        try {
            return DB::transaction(function () use ($request, $enrollment) {
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
                        $intakeYear = now()->year;
                        $studentNumber = app(\App\Services\StudentNumberService::class)->createSequence($intakeYear);
                        $student = Student::create([
                            'user_id' => $user->id,
                            'student_id_number' => $studentNumber,
                        ]);
                        $enrollment->update(['student_id' => $student->id]);
                    }
                }

                if ($request->filled('student_number')) {
                    if ($student) {
                        $student->update(['student_id_number' => $request->student_number]);
                    }
                }

                // Reload to get fresh data
                $enrollment->load('student.user');

                return response()->json([
                    'success' => true,
                    'data' => [
                        'student_name' => $enrollment->student?->user?->name ?? $request->student_name ?? '',
                        'student_number' => $enrollment->student?->student_id_number ?? $request->student_number ?? '',
                    ],
                ]);
            });
        } catch (\Exception $e) {
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
            $studentNum = $enr->student?->student_id_number ?? '';
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
     * Now saves student names AND correctly maps columns by label+type.
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
        // Header format: Student Name, Student ID, label1 (type1), label2 (type2), ..., Total, Grade, Remarks
        // Parse header to build a map of csv_column_index => [label, type]
        $csvColumnMap = []; // index => ['label' => ..., 'type' => ...]
        for ($h = 2; $h < count($header) - 3; $h++) {
            $colHeader = trim($header[$h]);
            // Parse "Label (type)" format
            if (preg_match('/^(.+)\s*\(([^)]+)\)$/', $colHeader, $matches)) {
                $csvColumnMap[$h] = [
                    'label' => trim($matches[1]),
                    'type' => strtolower(trim($matches[2])),
                ];
            } else {
                // Fallback: use the raw header as the label
                $csvColumnMap[$h] = [
                    'label' => $colHeader,
                    'type' => 'unknown',
                ];
            }
        }

        $offeringIds = SubjectOffering::where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->pluck('id');

        DB::beginTransaction();
        try {
            for ($i = 1; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if (empty($line)) continue;
                $data = str_getcsv($line);
                if (count($data) < 2) continue;

                $studentName = trim($data[0] ?? '');
                $studentNumber = trim($data[1] ?? '');

                // Find the enrollment by student number first, then by name
                $enrollment = null;
                if ($studentNumber) {
                    $enrollment = StudentSubjectEnrollment::whereIn('subject_offering_id', $offeringIds)
                        ->whereHas('student', fn($q) => $q->where('student_id_number', $studentNumber))
                        ->first();
                }

                // Fallback: try to find by student name
                if (!$enrollment && $studentName) {
                    $enrollment = StudentSubjectEnrollment::whereIn('subject_offering_id', $offeringIds)
                        ->whereHas('student.user', fn($q) => $q->where('name', $studentName))
                        ->first();
                }

                if (!$enrollment) {
                    // Student not found — create a new one!
                    $offering = SubjectOffering::whereIn('id', $offeringIds)->first();
                    if (!$offering) continue;

                    $studentRoleId = Role::where('slug', 'student')->value('id');
                    $user = User::create([
                        'name' => $studentName ?: 'Imported Student',
                        'email' => 'imported_' . uniqid() . '@example.com',
                        'password' => bcrypt('password'),
                        'role_id' => $studentRoleId,
                        'status' => 'active',
                    ]);

                    $intakeYear = now()->year;
                    $studentNum = app(\App\Services\StudentNumberService::class)->createSequence($intakeYear);

                    $student = Student::create([
                        'user_id' => $user->id,
                        'student_id_number' => $studentNum,
                    ]);

                    $studentClassHistory = $this->getOrCreateStudentClassHistory($offering, $student);

                    $enrollment = StudentSubjectEnrollment::create([
                        'student_id' => $student->id,
                        'student_class_history_id' => $studentClassHistory->id,
                        'subject_offering_id' => $offering->id,
                        'status' => 'enrolled',
                    ]);

                    $score = Score::create(['student_subject_enrollment_id' => $enrollment->id]);
                }

                // Save the student name from CSV to the database
                if ($studentName && $enrollment->student && $enrollment->student->user) {
                    $enrollment->student->user->update(['name' => $studentName]);
                }

                // Ensure score exists
                if (!$enrollment->score) {
                    $score = Score::create(['student_subject_enrollment_id' => $enrollment->id]);
                } else {
                    $score = $enrollment->score;
                }

                // Build a lookup map of label_type => detail_id for this student's score
                // This matches the same approach used in bySubjectAndTerm()
                $detailMap = [];
                $existingDetails = ScoreDetail::with('assessmentType')
                    ->where('score_id', $score->id)
                    ->get();

                foreach ($existingDetails as $d) {
                    $key = $d->label . '_' . ($d->assessmentType?->code ?? 'unknown');
                    $detailMap[$key] = $d;
                }

                // Map CSV columns to ScoreDetails by label+type
                foreach ($csvColumnMap as $csvIdx => $colInfo) {
                    if (!isset($data[$csvIdx]) || $data[$csvIdx] === '') continue;

                    $lookupKey = $colInfo['label'] . '_' . $colInfo['type'];
                    $detail = $detailMap[$lookupKey] ?? null;

                    if ($detail) {
                        $mark = (float) $data[$csvIdx];
                        if ($mark >= 0 && $mark <= 100) {
                            $detail->update(['mark' => $mark]);
                        }
                    }
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

    /**
     * POST /spreadsheet/subject/{subject}/term/{term}/import-file
     * Import a CSV, Excel, or PDF file with student names and scores.
     * The frontend parses Excel/PDF and sends structured JSON data.
     * For CSV files, the backend parses them directly.
     */
    public function importFile(Request $request, Subject $subject, Term $term): JsonResponse
    {
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());

            if ($extension === 'csv') {
                $content = file_get_contents($file->getRealPath());
                // Reuse the CSV import logic
                $request->merge(['csv_content' => $content]);
                return $this->importFromGoogleSheets($request, $subject, $term);
            }

            return response()->json([
                'message' => 'Unsupported file format. Please upload a CSV file, or use the Score Sheet import for Excel/PDF files.',
            ], 400);
        }

        // Accept parsed JSON data from frontend (for Excel/PDF that is parsed client-side)
        $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.student_name' => 'required|string|max:255',
            'rows.*.student_number' => 'nullable|string|max:50',
            'rows.*.marks' => 'nullable|array',
        ]);

        $rows = $request->rows;
        $offeringIds = SubjectOffering::where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->pluck('id');

        DB::beginTransaction();
        try {
            // Get the existing columns for this subject+term
            $existingEnrollments = StudentSubjectEnrollment::with([
                'student.user',
                'score.details.assessmentType',
            ])->whereIn('subject_offering_id', $offeringIds)->get();

            $columnsMap = collect();
            $existingEnrollments->each(function ($enr) use ($columnsMap) {
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

            $importedCount = 0;
            foreach ($rows as $rowData) {
                $studentName = trim($rowData['student_name'] ?? '');
                $studentNumber = trim($rowData['student_number'] ?? '');
                $marks = $rowData['marks'] ?? [];

                // Find existing enrollment or create new one
                $enrollment = null;
                if ($studentNumber) {
                    $enrollment = StudentSubjectEnrollment::whereIn('subject_offering_id', $offeringIds)
                        ->whereHas('student', fn($q) => $q->where('student_id_number', $studentNumber))
                        ->first();
                }

                if (!$enrollment && $studentName) {
                    $enrollment = StudentSubjectEnrollment::whereIn('subject_offering_id', $offeringIds)
                        ->whereHas('student.user', fn($q) => $q->where('name', $studentName))
                        ->first();
                }

                if (!$enrollment) {
                    $offering = SubjectOffering::whereIn('id', $offeringIds)->first();
                    if (!$offering) continue;

                    $studentRoleId = Role::where('slug', 'student')->value('id');
                    $user = User::create([
                        'name' => $studentName ?: 'Imported Student',
                        'email' => 'imported_' . uniqid() . '@example.com',
                        'password' => bcrypt('password'),
                        'role_id' => $studentRoleId,
                        'status' => 'active',
                    ]);

                    $studentNum = app(\App\Services\StudentNumberService::class)->createSequence(now()->year);
                    $student = Student::create([
                        'user_id' => $user->id,
                        'student_id_number' => $studentNum,
                    ]);

                    $studentClassHistory = $this->getOrCreateStudentClassHistory($offering, $student);
                    $enrollment = StudentSubjectEnrollment::create([
                        'student_id' => $student->id,
                        'student_class_history_id' => $studentClassHistory->id,
                        'subject_offering_id' => $offering->id,
                        'status' => 'enrolled',
                    ]);
                    Score::create(['student_subject_enrollment_id' => $enrollment->id]);
                }

                // Save student name
                if ($studentName && $enrollment->student && $enrollment->student->user) {
                    $enrollment->student->user->update(['name' => $studentName]);
                }

                // Ensure score exists
                if (!$enrollment->score) {
                    $score = Score::create(['student_subject_enrollment_id' => $enrollment->id]);
                } else {
                    $score = $enrollment->score;
                }

                // Map marks to ScoreDetails by label_type key
                $detailMap = [];
                $existingDetails = ScoreDetail::with('assessmentType')
                    ->where('score_id', $score->id)
                    ->get();

                foreach ($existingDetails as $d) {
                    $key = $d->label . '_' . ($d->assessmentType?->code ?? 'unknown');
                    $detailMap[$key] = $d;
                }

                foreach ($marks as $labelType => $markValue) {
                    // labelType is in format "Label_type" (e.g., "Quiz 1_quiz")
                    $detail = $detailMap[$labelType] ?? null;
                    if ($detail) {
                        $mark = (float) $markValue;
                        if ($mark >= 0 && $mark <= 100) {
                            $detail->update(['mark' => $mark]);
                        }
                    }
                }

                $this->recalculateTotal($score->id);
                $importedCount++;
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => "Successfully imported {$importedCount} student(s).",
                'data' => ['imported_count' => $importedCount],
            ]);
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
            ->whereNotNull('score')
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

        $grade = GradeBoundary::getGrade($total) ?? 'F';
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
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentType;
use App\Models\RBAC\Role;
use App\Models\Score;
use App\Models\ScoreDetail;
use App\Models\Student;
use App\Models\StudentNumberSequence;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SpreadsheetController extends Controller
{
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

        // Select only needed columns to reduce data transfer
        $enrollments = StudentSubjectEnrollment::with([
            'student:id,user_id,student_number_sequence_id',
            'student.user:id,name',
            'student.studentNumberSequence:id,student_number',
            'score:id,student_subject_enrollment_id,total,grade',
            'score.details:id,score_id,assessment_type_id,label,mark,order_number,max_score',
            'score.details.assessmentType:id,code,name,weight_percent',
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
        $this->recalculateTotal($detail->score_id);

        return response()->json(['success' => true, 'data' => $detail->fresh()]);
    }

    /**
     * PUT /spreadsheet/subject/{subject}/term/{term}/enrollments/{enrollment}
     * Update a student's name and/or student number from the score sheet.
     * If the enrollment has no student yet (legacy blank rows), creates one on the fly.
     */
    public function updateStudentInfo(Request $request, Subject $subject, Term $term, StudentSubjectEnrollment $enrollment): JsonResponse
    {
        $request->validate([
            'student_name' => 'nullable|string|max:255',
            'student_number' => 'nullable|string|max:50',
        ]);

        DB::beginTransaction();
        try {
            $student = $enrollment->student;

            // If no student exists yet (legacy blank row), create one now
            if (!$student) {
                $student = $this->createPlaceholderStudent();
                $enrollment->update(['student_id' => $student->id]);
                $enrollment->refresh();
            }

            if ($request->filled('student_name')) {
                $student->user->update(['name' => $request->student_name]);
            }

            if ($request->filled('student_number')) {
                if ($student->studentNumberSequence) {
                    // Check if the student number is already taken by another student
                    $existingSequence = StudentNumberSequence::where('student_number', $request->student_number)
                        ->where('id', '!=', $student->studentNumberSequence->id)
                        ->first();

                    if ($existingSequence) {
                        DB::rollBack();
                        return response()->json([
                            'message' => "Student ID '{$request->student_number}' is already taken by another student. Please use a different ID.",
                        ], 409);
                    }

                    $student->studentNumberSequence->update(['student_number' => $request->student_number]);
                }
            }

            DB::commit();

            // Refresh to get latest data
            $student->refresh();
            $enrollment->refresh();

            return response()->json([
                'success' => true,
                'data' => [
                    'student_name' => $student->user?->name ?? 'N/A',
                    'student_number' => $student->studentNumberSequence?->student_number ?? '',
                    'enrollment_id' => $enrollment->id,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to update student info: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a placeholder User + Student for blank rows.
     */
    private function createPlaceholderStudent(): Student
    {
        // Use a short unique ID (13 hex chars from uniqid) to stay within
        // the 20-character limit of the student_number column.
        $shortId = uniqid();
        $email = 'temp_' . $shortId . '@placeholder.local';

        $studentRole = Role::where('slug', 'student')->first();

        $user = User::create([
            'name' => 'New Student',
            'email' => $email,
            'password' => bcrypt($shortId),
            'role_id' => $studentRole?->id,
            'status' => 'active',
        ]);

        // TEMP-xxxxxxxxxxxxx = ~18 chars — fits safely within 20-char limit
        $sequence = StudentNumberSequence::create([
            'intake_year' => now()->year,
            'student_number' => 'TEMP-' . $shortId,
        ]);

        return Student::create([
            'user_id' => $user->id,
            'student_number_sequence_id' => $sequence->id,
        ]);
    }

    /**
     * POST /spreadsheet/subject/{subject}/term/{term}/enrollments
     * Add a new student enrollment to all offerings of this subject+term.
     * If student_id is not provided, creates a placeholder student and enrollment.
     */
    public function addEnrollment(Request $request, Subject $subject, Term $term): JsonResponse
    {
        $request->validate([
            'student_id' => 'nullable|exists:students,id',
        ]);

        $offeringIds = SubjectOffering::where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->pluck('id');

        if ($offeringIds->isEmpty()) {
            return response()->json(['message' => 'No active offerings found for this subject and term.'], 404);
        }

        DB::beginTransaction();
        try {
            // If no student_id provided, create a placeholder student first
            $studentId = $request->student_id;
            if (!$studentId) {
                $student = $this->createPlaceholderStudent();
                $studentId = $student->id;
            }

            $enrollmentIds = [];
            foreach ($offeringIds as $offeringId) {
                // Check if enrollment already exists for this student+offering
                $existing = StudentSubjectEnrollment::where('subject_offering_id', $offeringId)
                    ->where('student_id', $studentId)
                    ->first();

                if ($existing) {
                    $enrollment = $existing;
                } else {
                    $enrollment = StudentSubjectEnrollment::create([
                        'student_id' => $studentId,
                        'subject_offering_id' => $offeringId,
                    ]);
                }

                $enrollmentIds[] = $enrollment->id;

                // Create an empty score if it doesn't exist
                if (!$enrollment->score) {
                    $score = Score::create([
                        'student_subject_enrollment_id' => $enrollment->id,
                    ]);
                }
            }
            DB::commit();

            $message = $request->student_id ? 'Student enrolled successfully.' : 'New student row added. You can now edit the name and ID.';
            return response()->json(['success' => true, 'message' => $message, 'enrollment_ids' => $enrollmentIds], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to add enrollment: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json(['message' => $e->getMessage()], 500);
        }
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
     * PATCH /spreadsheet/subject/{subject}/term/{term}/details/change-type
     * Change the assessment type of all score details with the given label in this subject+term.
     */
    public function changeDetailType(Request $request, Subject $subject, Term $term): JsonResponse
    {
        $request->validate([
            'label' => 'required|string|max:50',
            'old_type' => 'required|string|max:20',
            'new_type' => 'required|in:quiz,assignment,midterm,final,project,custom',
        ]);

        $assessmentType = AssessmentType::firstOrCreate(
            ['code' => $request->new_type],
            [
                'name' => ucfirst($request->new_type),
                'weight_percent' => match ($request->new_type) {
                    'quiz' => 10,
                    'assignment' => 20,
                    'project' => 20,
                    'midterm' => 20,
                    'final' => 30,
                    default => 10,
                },
                'is_active' => true,
            ]
        );

        DB::beginTransaction();
        try {
            $offeringIds = SubjectOffering::where('subject_id', $subject->id)
                ->where('term_id', $term->id)
                ->pluck('id');

            $enrollmentIds = StudentSubjectEnrollment::whereIn('subject_offering_id', $offeringIds)
                ->pluck('id');

            $scoreIds = Score::whereIn('student_subject_enrollment_id', $enrollmentIds)
                ->pluck('id');

            $oldAssessmentType = AssessmentType::where('code', $request->old_type)->first();

            if ($oldAssessmentType) {
                $updated = ScoreDetail::whereIn('score_id', $scoreIds)
                    ->where('label', $request->label)
                    ->where('assessment_type_id', $oldAssessmentType->id)
                    ->update(['assessment_type_id' => $assessmentType->id]);

                DB::commit();
                return response()->json([
                    'success' => true,
                    'data' => [
                        'updated_count' => $updated,
                        'message' => "Column type changed to '{$request->new_type}'.",
                    ],
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'data' => ['updated_count' => 0, 'message' => 'No matching columns found.']]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /spreadsheet/student-numbers
     * Returns all PNC-formatted student numbers, ordered by student_number.
     */
    public function studentNumbers(): JsonResponse
    {
        $numbers = StudentNumberSequence::where('student_number', 'like', 'PNC%')
            ->orderBy('student_number')
            ->pluck('student_number');

        return response()->json([
            'success' => true,
            'data' => $numbers,
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

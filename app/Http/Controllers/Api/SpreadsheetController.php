<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentType;
use App\Models\GradeBoundary;
use App\Models\Score;
use App\Models\ScoreDetail;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\Term;
use App\Services\StudentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SpreadsheetController extends Controller
{
    public function __construct(private readonly StudentImportService $studentImportService)
    {
    }

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
        $filtered = $subjects->filter(function ($subject) {
            return $subject->terms->isNotEmpty();
        })->values();

        // ─── Batch ALL enrollment counts in ONE query ────────────────
        // Before: N queries (one per subject-term combination).
        // After: 1 GROUP BY query.
        $allOfferingIds = $filtered->flatMap(fn ($s) => $s->offerings->pluck('id'));
        $enrollmentCounts = collect();
        if ($allOfferingIds->isNotEmpty()) {
            $enrollmentCounts = StudentSubjectEnrollment::whereIn('subject_offering_id', $allOfferingIds)
                ->selectRaw('subject_offering_id, COUNT(*) as count')
                ->groupBy('subject_offering_id')
                ->pluck('count', 'subject_offering_id');
        }

        // Build result using the batched count lookup
        $result = $filtered->map(function ($subject) use ($enrollmentCounts) {
            $terms = $subject->terms->map(function ($term) use ($subject, $enrollmentCounts) {
                $offerings = $subject->offerings->where('term_id', $term->id);
                $offeringIds = $offerings->pluck('id');

                return [
                    'term_id' => $term->id,
                    'term_name' => $term->name,
                    'academic_year_id' => $term->academic_year_id,
                    'academic_year' => $term->academicYear?->year ?? $term->academicYear?->name ?? null,
                    'teachers' => $offerings->pluck('teacher.user.name')->filter()->unique()->values(),
                    'classes' => $offerings->pluck('class.name')->filter()->unique()->values(),
                    'offering_ids' => $offeringIds,
                    'enrollment_count' => $offeringIds->sum(fn ($id) => (int) ($enrollmentCounts[$id] ?? 0)),
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
     * Uses caching for fast repeated loads; invalidated on any mutation.
     */
    public function bySubjectAndTerm(Subject $subject, Term $term): JsonResponse
    {
        $cacheKey = "spreadsheet_{$subject->id}_{$term->id}";

        // Try cache first; on miss, build and cache the result
        $responseData = Cache::remember($cacheKey, 60, function () use ($subject, $term) {
            return $this->buildSpreadsheetData($subject, $term);
        });

        return response()->json($responseData);
    }

    /**
     * Build the full spreadsheet response data for a subject + term.
     * This is the core logic shared between cache-fill and cache-warming.
     */
    private function buildSpreadsheetData(Subject $subject, Term $term): array
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
        $columnsMap = $this->collectColumnsMap($enrollments);

        // Brand-new sheet with no score details yet — seed the four default
        // assessment columns (after the frozen ID column) so teachers don't
        // have to add them one by one. Real ScoreDetail rows (with real IDs)
        // are created for every student by the backfill logic below.
        if ($columnsMap->isEmpty() && $enrollments->isNotEmpty()) {
            $defaultColumns = [
                ['code' => 'quiz', 'label' => 'Quiz 1', 'order_number' => 1],
                ['code' => 'assignment', 'label' => 'Assignment', 'order_number' => 2],
                ['code' => 'midterm', 'label' => 'Midterm', 'order_number' => 3],
                ['code' => 'final', 'label' => 'Final', 'order_number' => 4],
            ];
            $assessmentTypesByCode = AssessmentType::whereIn('code', array_column($defaultColumns, 'code'))
                ->get()->keyBy('code');

            foreach ($defaultColumns as $def) {
                $assessmentType = $assessmentTypesByCode->get($def['code']);
                if (!$assessmentType) continue;
                $columnsMap->put($def['code'], [
                    'id' => null,
                    'label' => $def['label'],
                    'type' => $def['code'],
                    'order_number' => $def['order_number'],
                    'max_score' => 100,
                    'assessment_type_id' => $assessmentType->id,
                ]);
            }
        }

        $columns = $columnsMap->values()->sortBy('order_number')->values();

        // ─── Batch create missing scores ────────────────────────────────
        $enrollmentIds = $enrollments->pluck('id');
        $existingScoreEnrollmentIds = Score::whereIn('student_subject_enrollment_id', $enrollmentIds)
            ->pluck('student_subject_enrollment_id');
        $missingScoreEnrollmentIds = $enrollmentIds->diff($existingScoreEnrollmentIds);

        $didInsertScores = false;
        if ($missingScoreEnrollmentIds->isNotEmpty()) {
            $now = now();
            $scoreInserts = $missingScoreEnrollmentIds->map(fn($eid) => [
                'student_subject_enrollment_id' => $eid,
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray();
            Score::insert($scoreInserts);
            $didInsertScores = true;
        }

        // ─── Batch create missing score_details ─────────────────────────
        $allScoreIds = Score::whereIn('student_subject_enrollment_id', $enrollmentIds)->pluck('id', 'student_subject_enrollment_id');
        $existingDetailTuples = collect();
        if ($columns->isNotEmpty()) {
            $existingDetailTuples = ScoreDetail::whereIn('score_id', $allScoreIds->values())
                ->whereIn('label', $columns->pluck('label')->unique())
                ->get(['score_id', 'label', 'assessment_type_id'])
                ->map(fn($d) => "{$d->score_id}_{$d->label}_{$d->assessment_type_id}");
        }

        $detailInserts = [];
        foreach ($allScoreIds as $enrollmentId => $scoreId) {
            foreach ($columns as $col) {
                $tupleKey = "{$scoreId}_{$col['label']}_{$col['assessment_type_id']}";
                if (!$existingDetailTuples->contains($tupleKey)) {
                    $detailInserts[] = [
                        'score_id' => $scoreId,
                        'assessment_type_id' => $col['assessment_type_id'],
                        'label' => $col['label'],
                        'max_score' => $col['max_score'],
                        // Raw insert() bypasses Eloquent mutators, so these must be the real
                        // column names ('sequence_number', 'score'), not the 'order_number'/
                        // 'mark' accessor/mutator aliases defined on the ScoreDetail model.
                        'sequence_number' => $col['order_number'],
                        'score' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        $didInsertDetails = !empty($detailInserts);
        if ($didInsertDetails) {
            foreach (array_chunk($detailInserts, 200) as $chunk) {
                ScoreDetail::insert($chunk);
            }
        }

        // Reload once if anything changed (not 2-3 times as before)
        if ($didInsertScores || $didInsertDetails) {
            $enrollments = StudentSubjectEnrollment::with([
                'student.user',
                'subjectOffering.class',
                'score.details.assessmentType',
            ])->whereIn('subject_offering_id', $offeringIds)->get();
        }

        // Rebuild columns from the reloaded data so any column created above
        // (e.g. the default four) carries its real ScoreDetail id, not null.
        $columns = $this->collectColumnsMap($enrollments)->values()->sortBy('order_number')->values();

        // Build rows - map each student's marks to the canonical column IDs
        $rows = $enrollments->map(function ($enr) use ($columns) {
            $detailMarks = [];
            $detailIdMap = [];

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

        return [
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
        ];
    }

    /**
     * Collect all unique score-detail columns across a set of enrollments,
     * deduplicated by label+assessment-type.
     */
    private function collectColumnsMap($enrollments): \Illuminate\Support\Collection
    {
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
        return $columnsMap;
    }

    /**
     * Invalidate AND immediately re-warm the spreadsheet cache.
     * This ensures the first request after a mutation is still fast.
     */
    private function invalidateSpreadsheetCache(Subject $subject, Term $term): void
    {
        $cacheKey = "spreadsheet_{$subject->id}_{$term->id}";
        // Re-build and cache immediately so the next visitor gets fresh data fast.
        // Must forget first — remember() only calls the closure on cache miss!
        // Silently fall back on failure — the cache will be rebuilt on the next GET.
        Cache::forget($cacheKey);
        try {
            Cache::remember($cacheKey, 60, function () use ($subject, $term) {
                return $this->buildSpreadsheetData($subject, $term);
            });
        } catch (\Throwable $e) {
            // Cache warming failed; cache will rebuild on next request
        }
    }

    /**
     * Invalidate all spreadsheet caches (used when weights are updated globally).
     */
    private function invalidateAllSpreadsheetCaches(): void
    {
        // Clear by tag pattern - flush the entire spreadsheet namespace
        $prefix = 'spreadsheet_';
        // Get all offering combinations and forget each
        $offerings = SubjectOffering::select('subject_id', 'term_id')
            ->distinct()
            ->get();
        foreach ($offerings as $o) {
            Cache::forget("{$prefix}{$o->subject_id}_{$o->term_id}");
        }
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
        $this->invalidateSpreadsheetCache($subject, $term);

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
            $this->invalidateSpreadsheetCache($subject, $term);
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

        $this->invalidateSpreadsheetCache($subject, $term);
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
            $this->invalidateSpreadsheetCache($subject, $term);
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
        $this->invalidateSpreadsheetCache($subject, $term);
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
            $this->invalidateSpreadsheetCache($subject, $term);
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
            // Invalidate all spreadsheet caches since weights affect all scores
            $this->invalidateAllSpreadsheetCaches();
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
            'student_name' => 'nullable|string|max:100',
            'student_number' => 'nullable|string|max:50',
            'email_domain' => 'nullable|string|max:255',
        ]);

        $offering = SubjectOffering::where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->first();

        if (!$offering) {
            return response()->json(['message' => 'No active offering found for this subject and term.'], 404);
        }

        try {
            return DB::transaction(function () use ($request, $offering, $subject, $term) {
                $student = null;

                if ($request->filled('student_id')) {
                    $student = Student::find($request->student_id);
                } else {
                    // Use findOrCreateStudent to deduplicate globally — checks by
                    // student_id_number first, then by name+generation, so the same
                    // student added to multiple subjects gets one record, not N.
                    $student = $this->studentImportService->findOrCreateStudent(
                        $request->student_number ?: null,
                        $request->student_name ?: null,
                        $offering->class?->generation_id,
                        $request->email_domain ?: null
                    );
                }

                $studentClassHistory = $this->getOrCreateStudentClassHistory($offering, $student);

                // Check if this student is already enrolled in this subject+term
                $existingEnrollment = StudentSubjectEnrollment::where('student_id', $student->id)
                    ->where('subject_offering_id', $offering->id)
                    ->first();

                if ($existingEnrollment) {
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'id' => $existingEnrollment->id,
                            'student_id' => $student->id,
                            'student_number' => $student->student_id_number ?? '',
                        ],
                    ]);
                }

                $enrollment = StudentSubjectEnrollment::create([
                    'student_id' => $student->id,
                    'student_class_history_id' => $studentClassHistory->id,
                    'subject_offering_id' => $offering->id,
                    'status' => 'enrolled',
                ]);

                Score::create(['student_subject_enrollment_id' => $enrollment->id]);

                $this->invalidateSpreadsheetCache($subject, $term);
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
            $this->invalidateSpreadsheetCache($subject, $term);
            return response()->json(['success' => true, 'message' => 'Enrollment deleted.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function getOrCreateStudentClassHistory(SubjectOffering $offering, Student $student): StudentClassHistory
    {
        // subject_offerings has no generation_id column of its own (SubjectOffering::generation()
        // is a dead relation) — the real path to an offering's generation is via its class.
        $generationId = $offering->class?->generation_id ?? $student->generation_id;

        return $this->studentImportService->assignActiveClass($student, $offering->class_id, $generationId);
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
            'email_domain' => 'nullable|string|max:255',
        ]);

        try {
            return DB::transaction(function () use ($request, $enrollment, $subject, $term) {
                $student = $enrollment->student;

                if ($request->filled('student_name')) {
                    $newName = $request->student_name;

                    // Auto-merge: if a student with this exact name already exists ANYWHERE
                    // (not just in this subject+term — that scoping was the actual bug, since
                    // it meant the same real student re-duplicated once per subject), reassign
                    // this enrollment to that student instead of creating a duplicate.
                    if ($student && $student->user?->name !== $newName) {
                        $offeringIds = SubjectOffering::where('subject_id', $subject->id)
                            ->where('term_id', $term->id)
                            ->where('status', 'active')
                            ->pluck('id');
                        $offering = SubjectOffering::whereIn('id', $offeringIds)->first();
                        $generationId = $offering?->class?->generation_id;
                        $normalizedName = mb_strtolower(trim($newName));

                        $matches = Student::whereHas('user', fn ($q) => $q->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName]))
                            ->when($generationId, fn ($q) => $q->where(fn ($q2) => $q2->where('generation_id', $generationId)->orWhereNull('generation_id')))
                            ->where('id', '!=', $student->id)
                            ->get();

                        // Only trust an unambiguous match, and only if that student doesn't
                        // already have an enrollment in this exact subject+term (a true
                        // same-subject duplicate should be reconciled manually, not guessed at).
                        $matchedStudent = $matches->count() === 1 ? $matches->first() : null;
                        if ($matchedStudent) {
                            $alreadyEnrolledHere = StudentSubjectEnrollment::where('student_id', $matchedStudent->id)
                                ->whereIn('subject_offering_id', $offeringIds)
                                ->where('id', '!=', $enrollment->id)
                                ->exists();
                            if ($alreadyEnrolledHere) {
                                $matchedStudent = null;
                            }
                        }

                        if ($matchedStudent) {
                            // Found a matching student — reassign this enrollment
                            $enrollment->update(['student_id' => $matchedStudent->id]);

                            // Clean up the orphaned placeholder student if it has no
                            // other enrollments and was auto-created (placeholder)
                            $oldStudent = $student;
                            if ($oldStudent && $oldStudent->is_placeholder) {
                                $remaining = StudentSubjectEnrollment::where('student_id', $oldStudent->id)->count();
                                if ($remaining === 0) {
                                    $oldStudent->user()->delete();
                                    $oldStudent->delete();
                                }
                            }

                            $enrollment->load('student.user');
                            $this->invalidateSpreadsheetCache($subject, $term);
                            return response()->json([
                                'success' => true,
                                'data' => [
                                    'student_name' => $enrollment->student?->user?->name ?? $newName,
                                    'student_number' => $enrollment->student?->student_id_number ?? '',
                                ],
                            ]);
                        }
                    }

                    // No merge needed — proceed with normal name update
                    if ($student) {
                        $student->user->update(['name' => $newName]);
                    } else {
                        // Create a new user + student for this enrollment, via the same
                        // shared service every other import path uses.
                        $student = $this->studentImportService->findOrCreateStudent(
                            null,
                            $newName,
                            null,
                            $request->email_domain ?: null
                        );
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

                $this->invalidateSpreadsheetCache($subject, $term);
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
     * Exports data in CSV format for Google Sheets integration.
     * Now includes Class, Total, and Grade columns for complete data export.
     */
    public function syncToGoogleSheets(Subject $subject, Term $term): JsonResponse
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

        // Build deduplicated columns
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
                        ]);
                    }
                }
            }
        });
        $columns = $columnsMap->values()->sortBy('order_number')->values();

        // Build CSV header with all columns
        $csv = '#,Student Name,Student ID,Class';
        foreach ($columns as $col) {
            $csv .= "," . $col['label'] . ' (' . $col['type'] . ')';
        }
        $csv .= ",Total,Grade\n";

        // Build data rows
        $rowNum = 1;
        foreach ($enrollments as $enr) {
            $name = str_replace(',', ' ', $enr->student?->user?->name ?? '');
            $studentNum = $enr->student?->student_id_number ?? '';
            $className = str_replace(',', ' ', $enr->subjectOffering?->class?->name ?? '');
            
            $csv .= "{$rowNum},{$name},{$studentNum},{$className}";

            // Create detail lookup for this student
            if ($enr->score) {
                $detailMap = [];
                foreach ($enr->score->details as $d) {
                    $key = $d->label . '_' . ($d->assessmentType?->code ?? 'unknown');
                    $detailMap[$key] = $d->mark;
                }
                foreach ($columns as $col) {
                    $key = $col['label'] . '_' . $col['type'];
                    $mark = $detailMap[$key] ?? null;
                    $csv .= "," . ($mark !== null ? $mark : '');
                }
                $csv .= ",{$enr->score->total},{$enr->score->grade}";
            } else {
                foreach ($columns as $col) {
                    $csv .= ",";
                }
                $csv .= ",,";
            }
            $csv .= "\n";
            $rowNum++;
        }

        // Return as downloadable CSV content
        return response()->json([
            'success' => true,
            'data' => [
                'csv_content' => $csv,
                'filename' => "scores-{$subject->subject_code}-{$term->name}.csv",
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
        // Bulk imports can create many new student accounts in one request; give this endpoint
        // more headroom than the default 30s so a large class import doesn't hit a hard timeout.
        set_time_limit(120);

        $request->validate([
            'csv_content' => 'required|string',
            'email_domain' => 'nullable|string|max:255',
        ]);

        $emailDomain = $request->email_domain ?: null;

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

        // Bcrypt at cost 12 takes a few hundred ms per call; hashing once and reusing it for
        // every placeholder account created during this import avoids multiplying that cost by
        // the row count, which was blowing past PHP's max_execution_time on larger imports.
        $defaultPasswordHash = bcrypt('password');

        // Canonical column set for this subject+term, keyed by label — used to backfill a
        // ScoreDetail row for students who don't already have one for a given column (e.g. a
        // brand-new student this same import just created). See resolveOrCreateScoreDetail().
        $canonicalColumns = collect();
        StudentSubjectEnrollment::with('score.details.assessmentType')
            ->whereIn('subject_offering_id', $offeringIds)
            ->get()
            ->each(function ($enr) use ($canonicalColumns) {
                if ($enr->score && $enr->score->details) {
                    foreach ($enr->score->details as $d) {
                        if (!$canonicalColumns->has($d->label)) {
                            $canonicalColumns->put($d->label, [
                                'assessment_type_id' => $d->assessment_type_id,
                                'max_score' => $d->max_score,
                                'order_number' => $d->order_number ?? 0,
                            ]);
                        }
                    }
                }
            });

        DB::beginTransaction();
        try {
            for ($i = 1; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if (empty($line)) continue;
                $data = str_getcsv($line);
                if (count($data) < 2) continue;

                $studentName = trim($data[0] ?? '');
                $studentNumber = trim($data[1] ?? '');

                $offering = SubjectOffering::whereIn('id', $offeringIds)->first();
                if (!$offering) continue;

                // Find or create the STUDENT globally — not scoped to this subject/term — so a
                // student already enrolled in other subjects is reused instead of re-duplicated.
                $student = $this->studentImportService->findOrCreateStudent($studentNumber ?: null, $studentName ?: null, $offering->class?->generation_id, $emailDomain, $defaultPasswordHash);

                // Then, separately, find or create THIS student's enrollment in this subject/term
                // (any of its class offerings — a student only ever needs one enrollment here).
                $enrollment = StudentSubjectEnrollment::where('student_id', $student->id)
                    ->whereIn('subject_offering_id', $offeringIds)
                    ->first();

                if (!$enrollment) {
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
                $labelOnlyMap = []; // label => [ScoreDetail, ...] — fallback for headers with no "(type)"
                $existingDetails = ScoreDetail::with('assessmentType')
                    ->where('score_id', $score->id)
                    ->get();

                foreach ($existingDetails as $d) {
                    $key = $d->label . '_' . ($d->assessmentType?->code ?? 'unknown');
                    $detailMap[$key] = $d;
                    $labelOnlyMap[$d->label][] = $d;
                }

                // Map CSV columns to ScoreDetails by label+type, creating the column (from the
                // canonical definition, or from this column's own type if it's brand new) when
                // this student has none yet — see resolveOrCreateScoreDetail().
                foreach ($csvColumnMap as $csvIdx => $colInfo) {
                    if (!isset($data[$csvIdx]) || $data[$csvIdx] === '') continue;

                    $detail = $this->resolveOrCreateScoreDetail($score, $detailMap, $labelOnlyMap, $canonicalColumns, $colInfo['label'], $colInfo['type']);

                    if ($detail) {
                        $mark = (float) $data[$csvIdx];
                        if ($mark >= 0 && $mark <= 100) {
                            $detail->update(['score' => $mark]);
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
        // See importFromGoogleSheets() — same headroom for the same reason.
        set_time_limit(120);

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
            'email_domain' => 'nullable|string|max:255',
        ]);

        $emailDomain = $request->email_domain ?: null;
        $rows = $request->rows;
        $offeringIds = SubjectOffering::where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->pluck('id');

        // See importFromGoogleSheets() — hash once and reuse for every placeholder account
        // created in this import instead of paying bcrypt's cost per row.
        $defaultPasswordHash = bcrypt('password');

        DB::beginTransaction();
        try {
            // Canonical column set for this subject+term, keyed by label — used to backfill a
            // ScoreDetail row for students who don't already have one for a given column
            // (typically a brand-new student created by this very import, who starts with
            // zero ScoreDetail rows). Without this, a new student's marks had nothing to
            // attach to and silently vanished even though existing students' same-named
            // columns matched fine.
            $existingEnrollments = StudentSubjectEnrollment::with([
                'student.user',
                'score.details.assessmentType',
            ])->whereIn('subject_offering_id', $offeringIds)->get();

            $canonicalColumns = collect(); // label => ['assessment_type_id', 'max_score', 'order_number']
            $existingEnrollments->each(function ($enr) use ($canonicalColumns) {
                if ($enr->score && $enr->score->details) {
                    foreach ($enr->score->details as $d) {
                        if (!$canonicalColumns->has($d->label)) {
                            $canonicalColumns->put($d->label, [
                                'assessment_type_id' => $d->assessment_type_id,
                                'max_score' => $d->max_score,
                                'order_number' => $d->order_number ?? 0,
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

                $offering = SubjectOffering::whereIn('id', $offeringIds)->first();
                if (!$offering) continue;

                // Find or create the STUDENT globally — not scoped to this subject/term — so a
                // student already enrolled in other subjects is reused instead of re-duplicated.
                $student = $this->studentImportService->findOrCreateStudent($studentNumber ?: null, $studentName ?: null, $offering->class?->generation_id, $emailDomain, $defaultPasswordHash);

                // Then, separately, find or create THIS student's enrollment in this subject/term.
                $enrollment = StudentSubjectEnrollment::where('student_id', $student->id)
                    ->whereIn('subject_offering_id', $offeringIds)
                    ->first();

                if (!$enrollment) {
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

                // Map marks to ScoreDetails by label_type key, falling back to matching by
                // label alone when that fails. The frontend tags every column it parses with
                // "_unknown" unless the header text literally contains this app's own
                // "(quiz)"-style suffix — which a teacher's own Excel/CSV file never will, they
                // just write "Quiz 1". Without this fallback, every mark from a normal,
                // human-written file silently fails to match any existing column and only the
                // student's name/ID come through.
                $detailMap = [];
                $labelOnlyMap = []; // label => [ScoreDetail, ...] — used only when the exact key misses
                $existingDetails = ScoreDetail::with('assessmentType')
                    ->where('score_id', $score->id)
                    ->get();

                foreach ($existingDetails as $d) {
                    $key = $d->label . '_' . ($d->assessmentType?->code ?? 'unknown');
                    $detailMap[$key] = $d;
                    $labelOnlyMap[$d->label][] = $d;
                }

                foreach ($marks as $labelType => $markValue) {
                    // labelType is in format "Label_type" (e.g., "Quiz 1_quiz")
                    $labelOnly = preg_replace('/_[^_]+$/', '', (string) $labelType);
                    preg_match('/_([^_]+)$/', (string) $labelType, $typeMatch);
                    $type = $typeMatch[1] ?? 'unknown';

                    $detail = $this->resolveOrCreateScoreDetail($score, $detailMap, $labelOnlyMap, $canonicalColumns, $labelOnly, $type);

                    if ($detail) {
                        $mark = (float) $markValue;
                        if ($mark >= 0 && $mark <= 100) {
                            $detail->update(['score' => $mark]);
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

    /**
     * Find the ScoreDetail this student already has for a given label, or create one — used by
     * every import path so a mark always lands somewhere instead of being silently dropped when
     * this is the first time anyone (in this row, or in this subject/term at all) has a column
     * with this label. $canonicalColumns is mutated in place as new labels are created, so later
     * rows in the same import benefit from columns the earlier rows just created — it isn't just
     * a frozen snapshot of columns that existed before the import started.
     */
    private function resolveOrCreateScoreDetail(
        Score $score,
        array $detailMap,
        array &$labelOnlyMap,
        \Illuminate\Support\Collection $canonicalColumns,
        string $label,
        string $type
    ): ?ScoreDetail {
        $lookupKey = $label . '_' . $type;
        if (isset($detailMap[$lookupKey])) {
            return $detailMap[$lookupKey];
        }

        $candidates = $labelOnlyMap[$label] ?? [];
        if (count($candidates) === 1) {
            return $candidates[0];
        }
        if (count($candidates) > 1) {
            // More than one column already shares this label with a different type —
            // don't guess which one this mark belongs to.
            return null;
        }

        // No column at all for this label yet on this student. Reuse the definition another
        // student in this subject/term already has for the same label (so everyone's "Quiz 1"
        // column matches up), or derive one fresh from this column's own type if this is the
        // very first time anyone has used this label.
        $canonical = $canonicalColumns->get($label);
        if (!$canonical) {
            $assessmentType = ($type && $type !== 'unknown')
                ? AssessmentType::where('code', $type)->first()
                : null;
            $canonical = [
                'assessment_type_id' => $assessmentType?->id,
                'max_score' => 100,
                'order_number' => $canonicalColumns->count(),
            ];
            $canonicalColumns->put($label, $canonical);
        }

        $detail = ScoreDetail::create([
            'score_id' => $score->id,
            'assessment_type_id' => $canonical['assessment_type_id'],
            'label' => $label,
            'max_score' => $canonical['max_score'],
            'order_number' => $canonical['order_number'],
            'mark' => null,
        ]);
        $labelOnlyMap[$label][] = $detail;

        return $detail;
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
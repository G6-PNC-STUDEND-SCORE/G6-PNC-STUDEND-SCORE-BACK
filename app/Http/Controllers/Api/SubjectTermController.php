<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubjectTermController extends Controller
{
    /**
     * GET /subject-terms
     * List all subjects with their assigned terms, teacher, and class.
     */
    public function index(): JsonResponse
    {
        $subjects = Subject::with(['terms', 'offerings.teacher.user', 'offerings.class', 'teachers.user'])
            ->orderByDesc('id')
            ->get();
        $terms = Term::orderBy('term_number')->get(['id', 'name', 'term_number']);

        $data = $subjects->map(function ($subject) {
            $firstOffering = $subject->offerings->first();

            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'status' => $subject->status,
                'term_ids' => $subject->terms->pluck('id'),
                'teacher_ids' => $subject->teachers->pluck('id'),
                'teachers' => $subject->teachers->map(fn($t) => [
                    'id' => $t->id,
                    'user' => ['name' => $t->user->name ?? null],
                ])->values(),
                'teacher' => $firstOffering?->teacher ? [
                    'user' => ['name' => $firstOffering->teacher->user->name ?? null],
                ] : null,
                'class' => $firstOffering?->class ? [
                    'name' => $firstOffering->class->name ?? null,
                ] : null,
                'offerings' => $subject->offerings->map(fn($o) => [
                    'id' => $o->id,
                    'teacher_id' => $o->teacher_id,
                    'class_id' => $o->class_id,
                    'teacher' => $o->teacher ? [
                        'user' => ['name' => $o->teacher->user->name ?? null],
                    ] : null,
                    'class' => $o->class ? [
                        'name' => $o->class->name,
                    ] : null,
                ]),
                'terms' => $subject->terms->map(fn($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                ]),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'subjects' => $data,
                'terms' => $terms,
            ],
        ]);
    }

    /**
     * POST /subject-terms/sync
     * Sync the term assignments for all subjects in one batch.
     * Body: { assignments: [{ subject_id: 1, term_ids: [1, 2] }, ...] }
     */
    public function syncBatch(Request $request): JsonResponse
    {
        $request->validate([
            'assignments' => 'required|array',
            'assignments.*.subject_id' => 'required|exists:subjects,id',
            'assignments.*.term_ids' => 'present|array',
            'assignments.*.term_ids.*' => 'exists:terms,id',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->assignments as $assignment) {
                $subject = Subject::findOrFail($assignment['subject_id']);
                // Sync the pivot table - attach/detach as needed
                $subject->terms()->sync($assignment['term_ids']);
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Subject-term assignments updated successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /subject-terms/{subject}
     * Sync terms for a single subject.
     * Body: { term_ids: [1, 2] }
     */
    public function syncSubject(Request $request, Subject $subject): JsonResponse
    {
        $request->validate([
            'term_ids' => 'present|array',
            'term_ids.*' => 'exists:terms,id',
        ]);

        try {
            $subject->terms()->sync($request->term_ids);

            // Reload fresh term data to return correct IDs
            $subject->load('terms');

            return response()->json([
                'success' => true,
                'message' => "Term assignments updated for '{$subject->name}'.",
                'data' => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'term_ids' => $subject->terms->pluck('id'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubjectController extends Controller
{
    // GET /subjects — all authenticated users
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Subject::with(['offerings.teacher.user', 'offerings.class', 'offerings.term', 'teachers.user']);

        // Teacher only sees their own subjects (through offerings)
        if ($user->hasRole('teacher')) {
            $teacher = Teacher::where('user_id', $user->id)->first();
            if ($teacher) {
                $query->whereHas('offerings', function ($q) use ($teacher) {
                    $q->where('teacher_id', $teacher->id);
                });
            }
        }

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $query->orderByDesc('id');

        $subjects = $query->get()->map(function (Subject $subject) {
            $subject->setAttribute('teacher_ids', $subject->teachers->pluck('id'));
            return $subject;
        });

        return response()->json([
            'success' => true,
            'data'    => $subjects,
        ]);
    }

    // GET /subjects/{subject} — all authenticated users
    public function show(Subject $subject): JsonResponse
    {
        $subject->load(['offerings.teacher.user', 'offerings.class', 'teachers.user']);
        $subject->setAttribute('teacher_ids', $subject->teachers->pluck('id'));

        return response()->json([
            'success' => true,
            'data'    => $subject,
        ]);
    }

    // POST /subjects — admin only
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'subject_code' => 'nullable|string|max:50|unique:subjects,subject_code',
            'name'       => 'required|string|max:255',
            'credits'    => 'nullable|integer|min:0|max:255',
            'description'=> 'nullable|string',
            'department_id' => 'nullable|integer|exists:departments,id',
            'status'     => 'in:active,Active,inactive,Inactive',
            'teacher_ids'   => 'nullable|array',
            'teacher_ids.*' => 'integer|exists:teachers,id',
            'class_ids'     => 'nullable|array',
            'class_ids.*'   => 'integer|exists:classes,id',
            'term_ids'      => 'nullable|array',
            'term_ids.*'    => 'integer|exists:terms,id',
        ]);

        $request->merge(['status' => ucfirst(strtolower($request->status ?? 'Active'))]);

        $subject = Subject::create([
            'subject_code' => $request->subject_code ?: $this->generateSubjectCode($request->name),
            'name'       => $request->name,
            'credits'    => $request->credits,
            'description'=> $request->description,
            'department_id' => $request->department_id,
            'status'     => $request->status ?? 'Active',
        ]);

        if ($request->has('teacher_ids')) {
            $subject->teachers()->sync($request->teacher_ids ?? []);
        }

        // Sync terms BEFORE class offerings so syncClassOfferings can find them
        if ($request->has('term_ids')) {
            $subject->terms()->sync($request->term_ids ?? []);
        }

        // Create SubjectOffering records for each class_id
        if ($request->filled('class_ids')) {
            $this->syncClassOfferings($subject, $request->class_ids);
        }

        $subject->load(['offerings', 'teachers.user']);
        $subject->setAttribute('teacher_ids', $subject->teachers->pluck('id'));

        return response()->json([
            'success' => true,
            'data'    => $subject,
            'message' => 'Subject created successfully',
        ], 201);
    }

    // PUT /subjects/{subject} — admin only
    public function update(Request $request, Subject $subject): JsonResponse
    {
        $request->validate([
            'subject_code' => 'sometimes|string|max:50|unique:subjects,subject_code,'.$subject->id,
            'name'       => 'sometimes|string|max:255',
            'credits'    => 'nullable|integer|min:0|max:255',
            'description'=> 'nullable|string',
            'department_id' => 'nullable|integer|exists:departments,id',
            'status'     => 'sometimes|in:active,Active,inactive,Inactive',
            'teacher_ids'   => 'sometimes|array',
            'teacher_ids.*' => 'integer|exists:teachers,id',
            'class_ids'     => 'sometimes|array',
            'class_ids.*'   => 'integer|exists:classes,id',
        ]);

        if ($request->has('status')) {
            $request->merge(['status' => ucfirst(strtolower($request->status))]);
        }

        $subject->update($request->only('subject_code', 'name', 'credits', 'description', 'department_id', 'status'));

        if ($request->has('teacher_ids')) {
            $subject->teachers()->sync($request->teacher_ids ?? []);
        }

        // Sync class offerings
        if ($request->has('class_ids')) {
            $this->syncClassOfferings($subject, $request->class_ids ?? []);
        }

        $subject = $subject->fresh()->load(['offerings', 'teachers.user']);
        $subject->setAttribute('teacher_ids', $subject->teachers->pluck('id'));

        return response()->json([
            'success' => true,
            'data'    => $subject,
            'message' => 'Subject updated successfully',
        ]);
    }

    // DELETE /subjects/{subject} — admin only
    public function destroy(Subject $subject): JsonResponse
    {
        $subject->delete();
        return response()->json([
            'success' => true,
            'message' => 'Subject deleted successfully',
        ]);
    }

    // GET /teachers — admin & teacher
    public function teachers(): JsonResponse
    {
        $teachers = Teacher::with('user', 'department')->get()
            ->map(fn($t) => [
                'id'   => $t->id,
                'name' => $t->user->name,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $teachers,
        ]);
    }

    private function generateSubjectCode(string $name): string
    {
        $base = Str::upper(Str::substr(Str::slug($name, ''), 0, 12)) ?: 'SUBJECT';
        $code = $base;
        $suffix = 1;

        while (Subject::where('subject_code', $code)->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }

    /**
     * Sync SubjectOffering records for the given class IDs.
     * Creates offerings for each class + active term combination.
     * Removes offerings for classes no longer selected.
     */
    private function syncClassOfferings(Subject $subject, array $classIds): void
    {
        // Get the current active academic year
        $academicYear = \App\Models\AcademicYear::where('is_current', true)->first()
            ?? \App\Models\AcademicYear::orderByDesc('id')->first();

        if (!$academicYear) {
            return; // No academic year exists yet
        }

        // Get all active terms for this subject (via subject_term pivot).
        // If no terms are assigned yet, fall back to ALL terms in the current
        // academic year so class offerings are still created.
        $termIds = $subject->terms()->pluck('terms.id')->toArray();
        if (empty($termIds) && $academicYear) {
            $termIds = \App\Models\Term::where('academic_year_id', $academicYear->id)
                ->pluck('id')
                ->toArray();
        }

        // Get existing offerings for this subject
        $existingOfferings = SubjectOffering::where('subject_id', $subject->id)->get();

        // Build set of desired (class_id, term_id) pairs
        $desired = [];
        foreach ($classIds as $classId) {
            foreach ($termIds as $termId) {
                $desired[$classId . '_' . $termId] = true;
            }
        }

        // Remove offerings for classes no longer selected (or terms no longer assigned)
        foreach ($existingOfferings as $offering) {
            $key = $offering->class_id . '_' . $offering->term_id;
            if (!isset($desired[$key])) {
                // Skip deletion if the offering has student enrollments (FK restrictOnDelete)
                if (!$offering->enrollments()->exists()) {
                    $offering->delete();
                }
            }
        }

        // Create missing offerings
        foreach ($classIds as $classId) {
            foreach ($termIds as $termId) {
                $key = $classId . '_' . $termId;
                $alreadyExists = $existingOfferings->contains(
                    fn($o) => $o->class_id == $classId && $o->term_id == $termId
                );
                if (!$alreadyExists) {
                    SubjectOffering::create([
                        'subject_id' => $subject->id,
                        'class_id'   => $classId,
                        'term_id'    => $termId,
                        'academic_year_id' => $academicYear->id,
                        'status'     => 'active',
                    ]);
                }
            }
        }
    }
}

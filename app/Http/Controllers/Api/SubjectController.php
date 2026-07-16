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
        ]);

        if ($request->has('status')) {
            $request->merge(['status' => ucfirst(strtolower($request->status))]);
        }

        $subject->update($request->only('subject_code', 'name', 'credits', 'description', 'department_id', 'status'));

        if ($request->has('teacher_ids')) {
            $subject->teachers()->sync($request->teacher_ids ?? []);
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
}

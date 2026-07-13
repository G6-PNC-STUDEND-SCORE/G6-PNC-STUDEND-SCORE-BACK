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
<<<<<<< HEAD
    private function normalizeIsActive($value): int
    {
        // DB column is integer (0/1). Frontend sends 'Active'/'Inactive'.
        if ($value === 'Active' || $value === true || $value === 1 || $value === '1') {
            return 1;
        }

        if ($value === 'Inactive' || $value === false || $value === 0 || $value === '0') {
            return 0;
        }

        // Fallback: treat unknown as Active(1)
        return 1;
    }

    public function index(Request $request)
=======
    // GET /subjects — all authenticated users
    public function index(Request $request): JsonResponse
>>>>>>> 2f7a714627b59ef1f7770da653c5690dab9f8268
    {
        $user  = $request->user();
        $query = Subject::with(['offerings.teacher.user', 'offerings.class', 'offerings.term']);

<<<<<<< HEAD
        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('teacher', 'like', "%{$search}%")
                    ->orWhere('class', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('is_active', $this->normalizeIsActive($request->status));
=======
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
>>>>>>> 2f7a714627b59ef1f7770da653c5690dab9f8268
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get(),
        ]);
    }

    // GET /subjects/{subject} — all authenticated users
    public function show(Subject $subject): JsonResponse
    {
<<<<<<< HEAD
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'teacher' => 'nullable|string|max:255',
            'class' => 'required|string|max:50',
            'is_active' => 'required|in:Active,Inactive,1,0,true,false',
            'image' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $subjectData = $request->all();
$subjectData['code'] = $subjectData['code'] ?? ('SUB' . time()); // Generate default code if not provided
        $subjectData['credit_hours'] = $subjectData['credit_hours'] ?? 3; // Default to 3 credit hours if not provided
        $subjectData['is_active'] = $subjectData['is_active'] ?? 'Active'; // Default to Active if not provided

        // Set default value for teacher if empty
        if (empty($subjectData['teacher'])) {
            $subjectData['teacher'] = 'N/A';
        }

        // Normalize is_active to integer for DB
        $subjectData['is_active'] = $this->normalizeIsActive($subjectData['is_active']);

        $subject = Subject::create($subjectData);
=======
        return response()->json([
            'success' => true,
            'data'    => $subject->load(['offerings.teacher.user', 'offerings.class']),
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
            'status'     => 'in:Active,Inactive',
        ]);

        $subject = Subject::create([
            'subject_code' => $request->subject_code ?: $this->generateSubjectCode($request->name),
            'name'       => $request->name,
            'credits'    => $request->credits,
            'description'=> $request->description,
            'department_id' => $request->department_id,
            'status'     => $request->status ?? 'Active',
        ]);
>>>>>>> 2f7a714627b59ef1f7770da653c5690dab9f8268

        return response()->json([
            'success' => true,
            'data'    => $subject->load('offerings'),
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
            'status'     => 'in:Active,Inactive',
        ]);

        $subject->update($request->only('subject_code', 'name', 'credits', 'description', 'department_id', 'status'));

        return response()->json([
            'success' => true,
            'data'    => $subject->fresh()->load('offerings'),
            'message' => 'Subject updated successfully',
        ]);
    }

    // DELETE /subjects/{subject} — admin only
    public function destroy(Subject $subject): JsonResponse
    {
<<<<<<< HEAD
        $subject = Subject::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:subjects,code,' . $id,
            'teacher' => 'nullable|string|max:255',
            'class' => 'required|string|max:50',
            'credit_hours' => 'nullable|integer|min:1|max:10',
            'is_active' => 'required|in:Active,Inactive,1,0,true,false',
            'image' => 'nullable|string|max:255',
=======
        $subject->delete();
        return response()->json([
            'success' => true,
            'message' => 'Subject deleted successfully',
>>>>>>> 2f7a714627b59ef1f7770da653c5690dab9f8268
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

<<<<<<< HEAD
        $subjectData = $request->all();

        // Set default value for teacher if empty
        if (empty($subjectData['teacher'])) {
            $subjectData['teacher'] = 'N/A';
        }

        // Normalize is_active to integer for DB
        if (isset($subjectData['is_active'])) {
            $subjectData['is_active'] = $this->normalizeIsActive($subjectData['is_active']);
        }

        $subject->update($subjectData);

        return response()->json([
            'success' => true,
            'message' => 'Subject updated successfully',
            'data' => $subject
        ]);
    }

    public function destroy($id)
    {
        $subject = Subject::findOrFail($id);
        $subject->delete();

        return response()->json([
            'success' => true,
            'message' => 'Subject deleted successfully'
        ]);
    }

    public function teachers()
    {
        // Get distinct teacher names from subjects table
        $teachers = Subject::select('teacher')
            ->whereNotNull('teacher')
            ->where('teacher', '!=', '')
            ->distinct()
            ->orderBy('teacher')
            ->pluck('teacher');

        return response()->json([
            'success' => true,
            'data' => $teachers
        ]);
=======
        return $code;
>>>>>>> 2f7a714627b59ef1f7770da653c5690dab9f8268
    }
}


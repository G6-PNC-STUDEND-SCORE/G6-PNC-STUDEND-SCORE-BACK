<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Score;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\StudentSubjectEnrollment;
use App\Services\StudentNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentNumberService $studentNumberService
    ) {}

    // GET /students — admin & teacher see all, student sees only themselves
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Student::with(['user', 'generation', 'classHistories.class']);

        if ($user->hasRole('student')) {
            $query->where('user_id', $user->id);
        } else {
            // Students auto-created as a side effect of adding a score-sheet row or importing
            // a scores file/Google Sheet shouldn't clutter this management list — they still
            // work fine for scoring, they just don't need managing here.
            $query->where('is_placeholder', false);
        }

        $students = $query->get();

        return response()->json([
            'students' => $students,
        ]);
    }

    // GET /students/{student}
    public function show(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole('student') && $student->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'student' => $student->load([
                'user',
                'generation',
                'classHistories.class',
                'enrollments.subjectOffering.subject',
                'enrollments.subjectOffering.term',
                'enrollments.score.details',
            ]),
        ]);
    }

    // POST /students — admin only
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|max:255|unique:users,email',
            'password'      => 'required|string|min:6',
            'gender'        => 'nullable|in:Male,Female',
            'status'        => 'nullable|in:active,inactive',
            'generation_id' => 'nullable|exists:generations,id',
            'class_id'      => 'nullable|exists:classes,id',
        ]);

        DB::beginTransaction();
        try {
            // Create user
            $user = \App\Models\User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'gender'   => $request->gender ?? 'Male',
                'status'   => $request->status ?? 'active',
            ]);

            // Assign student role
            $studentRole = \App\Models\RBAC\Role::where('slug', 'student')->first();
            if ($studentRole) {
                $user->update(['role_id' => $studentRole->id]);
            }

            // Create student number
            $intakeYear = now()->year;
            $studentIdNumber = $this->studentNumberService->createSequence($intakeYear);

            // Create student record
            $student = Student::create([
                'user_id'           => $user->id,
                'student_id_number'  => $studentIdNumber,
                'generation_id'      => $request->generation_id,
            ]);

            // Create class history if class_id is provided
            if ($request->filled('class_id')) {
                $class = \App\Models\SchoolClass::find($request->class_id);
                $generationId = $class?->generation_id ?? $request->generation_id;

                StudentClassHistory::create([
                    'student_id'    => $student->id,
                    'class_id'      => $request->class_id,
                    'generation_id' => $generationId,
                    'start_date'    => now(),
                    'status'        => 'active',
                ]);
            }

            DB::commit();
            return response()->json([
                'student' => $student->load(['user', 'generation', 'classHistories.class']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // PUT /students/{student} — admin only
    public function update(Request $request, Student $student): JsonResponse
    {
        $request->validate([
            'generation_id' => 'nullable|exists:generations,id',
            'name'          => 'nullable|string|max:255',
            'email'         => 'nullable|email|max:255|unique:users,email,' . $student->user_id,
            'password'      => 'nullable|string|min:6',
            'gender'        => 'nullable|in:Male,Female,Other',
            'status'        => 'nullable|in:active,inactive,suspended',
            'class_id'      => 'nullable|exists:classes,id',
        ]);

        $student->update($request->only('generation_id'));

        // Update the user's fields
        $userData = [];
        if ($request->filled('name')) {
            $userData['name'] = $request->name;
        }
        if ($request->filled('email')) {
            $userData['email'] = $request->email;
        }
        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }
        if ($request->filled('gender')) {
            $userData['gender'] = $request->gender;
        }
        if ($request->filled('status')) {
            $userData['status'] = $request->status;
        }
        if (!empty($userData)) {
            $student->user()->update($userData);
        }

        // Handle class assignment if class_id is provided
        if ($request->filled('class_id')) {
            // Deactivate any active class history
            StudentClassHistory::where('student_id', $student->id)
                ->where('status', 'active')
                ->update(['status' => 'transferred', 'end_date' => now()]);

            $class = \App\Models\SchoolClass::find($request->class_id);
            $generationId = $class?->generation_id ?? $student->generation_id;

            // Create new active class history
            StudentClassHistory::create([
                'student_id'    => $student->id,
                'class_id'      => $request->class_id,
                'generation_id' => $generationId,
                'start_date'    => now(),
                'status'        => 'active',
            ]);
        }

        return response()->json([
            'student' => $student->fresh()->load(['user', 'generation', 'classHistories.class']),
        ]);
    }

    // DELETE /students/{student} — admin only
    public function destroy(Student $student): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = $student->user;

            // 0. Get all class history IDs for this student
            $classHistoryIds = $student->classHistories()->pluck('id');

            // 1. Delete enrollments that reference these class histories (by class_history_id)
            //    This catches enrollments where student_id might be null
            $enrollmentsByHistory = StudentSubjectEnrollment::whereIn('student_class_history_id', $classHistoryIds)->get();
            foreach ($enrollmentsByHistory as $enrollment) {
                if ($enrollment->score) {
                    $enrollment->score->details()->delete();
                    $enrollment->score->delete();
                }
                $enrollment->delete();
            }

            // 2. Also delete any enrollments by student_id (in case student_id is set but class_history_id differs)
            $enrollmentsByStudent = StudentSubjectEnrollment::where('student_id', $student->id)->get();
            foreach ($enrollmentsByStudent as $enrollment) {
                if ($enrollment->score) {
                    $enrollment->score->details()->delete();
                    $enrollment->score->delete();
                }
                $enrollment->delete();
            }

            // 3. Delete class histories (safe now — no enrollments reference them)
            $student->classHistories()->delete();

            // 4. Delete report cards (cascadeOnDelete handles report_card_details)
            $student->reportCards()->delete();

            // 5. Delete transcripts (cascadeOnDelete handles transcript_details)
            $student->transcripts()->delete();

            // 6. Hard delete the student record
            $student->delete();

            // 7. Delete the associated user account
            if ($user) {
                $user->delete();
            }

            DB::commit();
            return response()->json(['message' => 'Student and all related data permanently deleted.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete student: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /students/bulk-delete
     * Delete multiple students at once.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:students,id',
        ]);

        $ids = $request->ids;
        $deleted = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($ids as $id) {
                $student = Student::find($id);
                if (!$student) continue;

                // Get class history IDs for this student
                $classHistoryIds = StudentClassHistory::where('student_id', $student->id)->pluck('id');

                // Delete enrollments referencing these class histories (by class_history_id)
                $enrollmentsByHistory = StudentSubjectEnrollment::whereIn('student_class_history_id', $classHistoryIds)->get();
                foreach ($enrollmentsByHistory as $enrollment) {
                    if ($enrollment->score) {
                        $enrollment->score->details()->delete();
                        $enrollment->score->delete();
                    }
                    $enrollment->delete();
                }

                // Also delete any remaining enrollments by student_id
                $enrollments = StudentSubjectEnrollment::where('student_id', $student->id)->get();
                foreach ($enrollments as $enrollment) {
                    if ($enrollment->score) {
                        $enrollment->score->details()->delete();
                        $enrollment->score->delete();
                    }
                    $enrollment->delete();
                }

                // Delete class histories (safe now)
                StudentClassHistory::where('student_id', $student->id)->delete();

                // Delete report cards (cascadeOnDelete handles report_card_details)
                \App\Models\ReportCard::where('student_id', $student->id)->delete();

                // Delete transcripts (cascadeOnDelete handles transcript_details)
                \App\Models\Transcript::where('student_id', $student->id)->delete();

                // Delete the student
                $user = $student->user;
                $student->delete();

                // Delete the associated user
                if ($user) {
                    $user->delete();
                }

                $deleted++;
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => "{$deleted} student(s) deleted successfully.",
                'data' => ['deleted_count' => $deleted],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete students: ' . $e->getMessage()], 500);
        }
    }

    // PUT /students/{student}/assign-class — using student_class_histories
    public function assignClass(Request $request, Student $student): JsonResponse
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
        ]);            // Deactivate any active class history
        StudentClassHistory::where('student_id', $student->id)
            ->where('status', 'active')
            ->update(['status' => 'transferred', 'end_date' => now()]);

        $class = \App\Models\SchoolClass::find($request->class_id);
        $generationId = $class?->generation_id ?? $student->generation_id;

        // Create new active class history
        StudentClassHistory::create([
            'student_id'    => $student->id,
            'class_id'      => $request->class_id,
            'generation_id' => $generationId,
            'start_date'    => now(),
            'status'        => 'active',
        ]);

        return response()->json([
            'student' => $student->fresh()->load(['user', 'generation', 'classHistories.class']),
        ]);
    }

    // POST /students/import — Bulk import students from Excel/JSON
    public function importBulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'students' => 'required|array|min:1',
            'students.*.name'   => 'required|string|max:255',
            'students.*.gender' => 'nullable|in:Male,Female',
            'students.*.status' => 'nullable|in:active,inactive',
            'students.*.class'  => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $imported = 0;
            $skipped = [];
            $intakeYear = now()->year;

            foreach ($data['students'] as $index => $row) {
                $studentName = $row['name'];
                $rowNumber = $index + 1;

                // Create or find user
                $user = \App\Models\User::firstOrCreate(
                    ['name' => $studentName],
                    [
                        'email'    => strtolower(str_replace(' ', '.', $studentName)) . '.' . uniqid() . '@student.edu',
                        'password' => Hash::make('password123'),
                        'gender'   => $row['gender'] ?? null,
                        'status'   => $row['status'] ?? 'active',
                    ]
                );

                // Check if this user is already linked to a student record
                $existingStudent = \App\Models\Student::where('user_id', $user->id)->first();
                if ($existingStudent) {
                    $skipped[] = [
                        'row'    => $rowNumber,
                        'name'   => $studentName,
                        'reason' => "Student already exists (ID: {$existingStudent->id}, User ID: {$user->id})",
                    ];
                    continue;
                }

                // Assign student role
                $studentRole = \App\Models\RBAC\Role::where('slug', 'student')->first();
                if ($studentRole && $user->role_id !== $studentRole->id) {
                    $user->update(['role_id' => $studentRole->id]);
                }

                // Create student number
                $studentIdNumber = $this->studentNumberService->createSequence($intakeYear);

                // Create student record
                $student = \App\Models\Student::create([
                    'user_id'           => $user->id,
                    'student_id_number'  => $studentIdNumber,
                ]);

                // Assign class if specified
                if (!empty($row['class'])) {
                    $class = \App\Models\SchoolClass::where('name', $row['class'])->first();
                    if ($class) {
                        \App\Models\StudentClassHistory::create([
                            'student_id' => $student->id,
                            'class_id'   => $class->id,
                            'start_date' => now(),
                            'status'     => 'active',
                        ]);
                    }
                }

                $imported++;
            }

            DB::commit();

            if (!empty($skipped)) {
                $skippedNames = collect($skipped)->pluck('name')->implode(', ');

                if ($imported === 0) {
                    // All students already exist — return an error so the user sees the message
                    $namesList = collect($skipped)->map(fn($s) => "'{$s['name']}'")->implode(', ');
                    return response()->json([
                        'message'  => "Import failed — student {$namesList} already exist" . (count($skipped) === 1 ? 's' : '') . ". Please remove " . (count($skipped) === 1 ? 'it' : 'them') . " from your file and try again.",
                        'imported' => 0,
                        'skipped'  => $skipped,
                    ], 409);
                }

                return response()->json([
                    'message'  => "Imported {$imported} student(s) successfully. Skipped " . count($skipped) . " existing record(s): {$skippedNames}.",
                    'imported' => $imported,
                    'skipped'  => $skipped,
                ]);
            }

            return response()->json([
                'message'  => "Successfully imported {$imported} student(s).",
                'imported' => $imported,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            $errorCode = $e->errorInfo[1] ?? null;

            if ($errorCode === 1062) {
                return response()->json([
                    'message' => 'Import failed — this student already exists in the system. Please remove duplicates from your file and try again.',
                ], 409);
            }

            if ($errorCode === 1452) {
                return response()->json([
                    'message' => 'Import stopped: A referenced record (e.g. class or user) was not found. Please check your data and try again.',
                ], 422);
            }

            return response()->json([
                'message' => 'Import failed due to a database error. Please check your file and try again.',
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Import failed unexpectedly: ' . $e->getMessage(),
            ], 500);
        }
    }

    // GET /students/{student}/scores — student sees own, teacher & admin see all
    public function scores(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole('student') && $student->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $scores = Score::with(['details', 'enrollment.subjectOffering.subject', 'enrollment.subjectOffering.term'])
            ->whereHas('enrollment', function ($q) use ($student) {
                $q->where('student_id', $student->id);
            })
            ->get();

        return response()->json($scores);
    }
}

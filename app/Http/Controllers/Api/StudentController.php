<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Score;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\StudentSubjectEnrollment;
use App\Services\StudentImportService;
use App\Services\StudentNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentNumberService $studentNumberService,
        private readonly StudentImportService $studentImportService
    ) {}

    // GET /students — admin & teacher see all, student sees only themselves
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Student::with(['user', 'generation', 'classHistories.class']);

        if ($user->hasRole('student')) {
            $query->where('user_id', $user->id);
        } else {
            // Show every student with at least one active enrollment — including those
            // created via score-sheet import (is_placeholder=true). That flag only ever
            // meant "auto-created rather than manually entered by an admin," not "hide
            // this student forever": once StudentImportService's global dedup (matching by
            // student_id_number, then name+generation) is in place, an imported student IS
            // a real student and belongs in this list like any other.
            $query->whereHas('enrollments', fn ($e) => $e->where('status', 'enrolled'));
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
            'email_domain'      => 'nullable|string|max:255',
        ]);

        $emailDomain = $data['email_domain'] ?? null;

        DB::beginTransaction();
        try {
            $imported = 0;
            $reused = [];

            foreach ($data['students'] as $index => $row) {
                $studentName = $row['name'];
                $rowNumber = $index + 1;

                $class = !empty($row['class']) ? \App\Models\SchoolClass::where('name', $row['class'])->first() : null;
                $generationId = $class?->generation_id;

                // Same global dedup choke point as the score-sheet import — matches by name
                // (scoped to generation when known) before creating a new student, so a student
                // already known to the system (e.g. via another subject's import) is reused
                // here instead of duplicated.
                $wasExisting = false;
                if ($studentName) {
                    $normalized = mb_strtolower(trim($studentName));
                    $wasExisting = \App\Models\Student::whereHas('user', fn ($q) => $q->whereRaw('LOWER(TRIM(name)) = ?', [$normalized]))
                        ->when($generationId, fn ($q) => $q->where(fn ($q2) => $q2->where('generation_id', $generationId)->orWhereNull('generation_id')))
                        ->exists();
                }

                $student = $this->studentImportService->findOrCreateStudent(
                    null,
                    $studentName,
                    $generationId,
                    $emailDomain
                );

                if ($wasExisting) {
                    $reused[] = ['row' => $rowNumber, 'name' => $studentName];
                }

                $userUpdates = array_filter([
                    'gender' => $row['gender'] ?? null,
                    'status' => $row['status'] ?? null,
                ]);
                if (!empty($userUpdates)) {
                    $student->user->update($userUpdates);
                }

                // Assign/update class if specified — reassigns rather than duplicating the
                // active class history when this student already has one.
                if ($class) {
                    $this->studentImportService->assignActiveClass($student, $class->id, $generationId);
                }

                $imported++;
            }

            DB::commit();

            if (!empty($reused)) {
                $reusedNames = collect($reused)->pluck('name')->implode(', ');
                return response()->json([
                    'message'  => "Imported {$imported} student(s). " . count($reused) . " matched existing record(s) and were updated instead of duplicated: {$reusedNames}.",
                    'imported' => $imported,
                    'reused'   => $reused,
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Score;
use App\Models\Student;
use App\Models\StudentClassHistory;
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

        $query = Student::with(['user', 'generation', 'studentNumberSequence', 'classHistories.class']);

        if ($user->hasRole('student')) {
            $query->where('user_id', $user->id);
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
                'studentNumberSequence',
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

            // Create student number sequence
            $intakeYear = now()->year;
            $sequence = $this->studentNumberService->createSequence($intakeYear);

            // Create student record
            $student = Student::create([
                'user_id'                    => $user->id,
                'student_number_sequence_id' => $sequence->id,
                'generation_id'              => $request->generation_id,
            ]);

            DB::commit();
            return response()->json([
                'student' => $student->load(['user', 'generation', 'studentNumberSequence']),
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

        return response()->json([
            'student' => $student->fresh()->load(['user', 'generation', 'studentNumberSequence']),
        ]);
    }

    // DELETE /students/{student} — admin only
    public function destroy(Student $student): JsonResponse
    {
        DB::beginTransaction();
        try {
            $student->load(['enrollments.score', 'reportCards', 'transcripts']);

            $user = $student->user;
            $studentNumberSequence = $student->studentNumberSequence;

            // 1. Delete scores (score_details cascades via DB)
            foreach ($student->enrollments as $enrollment) {
                if ($enrollment->score) {
                    $enrollment->score->details()->delete();
                    $enrollment->score->delete();
                }
            }

            // 2. Delete enrollments
            $student->enrollments()->delete();

            // 3. Delete class histories
            $student->classHistories()->delete();

            // 4. Delete report card details & report cards
            foreach ($student->reportCards as $reportCard) {
                $reportCard->details()->delete();
            }
            $student->reportCards()->delete();

            // 5. Delete transcript details & transcripts
            foreach ($student->transcripts as $transcript) {
                $transcript->details()->delete();
            }
            $student->transcripts()->delete();

            // 6. Hard delete the student record
            $student->delete();

            // 7. Delete student number sequence
            if ($studentNumberSequence) {
                $studentNumberSequence->delete();
            }

            // 8. Delete the associated user account
            // (Student is already deleted in step 6, so the DB cascade on students.user_id is a no-op)
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
            'student' => $student->fresh()->load(['user', 'generation', 'studentNumberSequence', 'classHistories.class']),
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

                // Create student number sequence
                $sequence = $this->studentNumberService->createSequence($intakeYear);

                // Create student record
                $student = \App\Models\Student::create([
                    'user_id'                    => $user->id,
                    'student_number_sequence_id' => $sequence->id,
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

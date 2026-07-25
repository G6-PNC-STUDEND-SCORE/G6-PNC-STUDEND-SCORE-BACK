<?php

namespace App\Services;

use App\Models\RBAC\Role;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Single shared choke point for "find the existing student this import row
 * refers to, or create a new one" — used by every student/user creation path
 * that runs during score-sheet or bulk import (Spreadsheet, Google Sheets,
 * Students-page bulk import), so a student enrolled in many subjects gets one
 * record instead of one per subject/import.
 */
class StudentImportService
{
    public function __construct(private readonly StudentNumberService $studentNumberService)
    {
    }

    public function findOrCreateStudent(
        ?string $studentIdNumber,
        ?string $studentName,
        ?int $generationId,
        ?string $emailDomain = null,
        ?string $passwordHash = null
    ): Student {
        $studentIdNumber = $studentIdNumber !== null ? trim($studentIdNumber) : null;
        $studentName = $studentName !== null ? trim($studentName) : null;

        if ($studentIdNumber) {
            $existing = Student::where('student_id_number', $studentIdNumber)->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($studentName) {
            $matches = Student::whereHas('user', function ($q) use ($studentName) {
                    $q->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($studentName)]);
                })
                ->when($generationId, fn ($q) => $q->where(function ($q2) use ($generationId) {
                    $q2->where('generation_id', $generationId)->orWhereNull('generation_id');
                }))
                ->get();

            // Only trust an unambiguous match — if this name already belongs to more than one
            // student, don't guess which one it is; fall through to creating a new record
            // rather than risk silently merging two different people's data.
            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        $studentRoleId = Role::where('slug', 'student')->value('id');
        $user = User::create([
            'name' => $studentName ?: 'Imported Student',
            'email' => $this->buildEmail($studentName, $emailDomain),
            'password' => $passwordHash ?? bcrypt('password'),
            'role_id' => $studentRoleId,
            'status' => 'active',
        ]);

        return Student::create([
            'user_id' => $user->id,
            'student_id_number' => $studentIdNumber ?: $this->studentNumberService->createSequence(now()->year),
            'generation_id' => $generationId,
            'is_placeholder' => true,
        ]);
    }

    /**
     * Get the student's current active class history, or rotate them into a new one — used by
     * every import/enrollment path that assigns a student to a class, so re-importing the same
     * student doesn't create a second active StudentClassHistory row.
     */
    public function assignActiveClass(Student $student, int $classId, ?int $generationId): StudentClassHistory
    {
        $generationId ??= $student->generation_id;

        $history = StudentClassHistory::where('student_id', $student->id)
            ->where('class_id', $classId)
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
            'class_id' => $classId,
            'generation_id' => $generationId,
            'start_date' => now(),
            'status' => 'active',
        ]);
    }

    /**
     * Build a real, unique email under the given Sign-in Domain (e.g. "sok.dara@student.example.org")
     * instead of a synthetic @example.com address — so the account can actually be signed into via
     * Google login later, matched by the same domain rule. Falls back to a synthetic placeholder
     * address only when no domain was supplied (e.g. an older caller that hasn't been updated yet).
     */
    private function buildEmail(?string $name, ?string $domain): string
    {
        if (!$domain) {
            return 'imported_' . uniqid() . '@example.com';
        }

        $slug = Str::of($name ?: 'student')
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.');

        $base = $slug->isEmpty() ? 'student' : (string) $slug;
        $domain = Str::lower(trim($domain, " \t\n\r\0\x0B@"));

        $email = "{$base}@{$domain}";
        $suffix = 1;
        while (User::where('email', $email)->exists()) {
            $suffix++;
            $email = "{$base}{$suffix}@{$domain}";
        }

        return $email;
    }
}

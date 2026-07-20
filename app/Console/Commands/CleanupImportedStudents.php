<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupImportedStudents extends Command
{
    protected $signature = 'students:cleanup-imported 
        {--dry-run : Show what would be deleted without actually deleting}
        {--force : Skip confirmation prompt}';

    protected $description = 'Find and remove Student/User records created by scoresheet imports, migrating data to enrollment imported_name/imported_number fields';

    /**
     * Email patterns used by old import code to auto-generate User records.
     */
    private array $importPatterns = [
        'pending_student_%@example.com',
        'imported_%@example.com',
        'student_%@example.com',
        '%@student.edu',
    ];

    public function handle(): int
    {
        $users = collect();

        foreach ($this->importPatterns as $pattern) {
            $matches = User::where('email', 'like', $pattern)->get();
            $users = $users->concat($matches);
        }

        // Also catch users with the 'Imported Student' placeholder name
        $nameMatches = User::where('name', 'Imported Student')->get();
        $users = $users->concat($nameMatches);

        // Deduplicate by ID
        $users = $users->unique('id')->values();

        if ($users->isEmpty()) {
            $this->info('No imported student records found. Database is clean!');
            return Command::SUCCESS;
        }

        $this->warn("Found {$users->count()} imported student user(s) to process.");

        // Show a summary table
        $rows = [];
        foreach ($users as $user) {
            $student = Student::where('user_id', $user->id)->first();
            $enrollmentCount = $student
                ? StudentSubjectEnrollment::where('student_id', $student->id)->count()
                : 0;
            $rows[] = [
                $user->id,
                $student?->id ?? '—',
                $user->name ?: '(empty)',
                $user->email,
                $enrollmentCount,
            ];
        }
        $this->table(['User ID', 'Student ID', 'Name', 'Email', 'Enrollments'], $rows);

        if ($this->option('dry-run')) {
            $totalEnrollments = collect($rows)->sum(fn($r) => $r[4]);
            $this->warn("[DRY-RUN] Would migrate {$totalEnrollments} enrollment(s) and delete {$users->count()} user/student record(s).");
            $this->warn('Run without --dry-run and with --force to execute.');
            return Command::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('This will migrate enrollment data and DELETE these user/student records. Continue?')) {
            $this->info('Cancelled.');
            return Command::SUCCESS;
        }

        $deleted = 0;
        $errors = 0;

        foreach ($users as $user) {
            $student = Student::where('user_id', $user->id)->first();

            DB::beginTransaction();
            try {
                if ($student) {
                    // 1. Migrate student name/number to enrollment imported fields
                    $enrollments = StudentSubjectEnrollment::where('student_id', $student->id)->get();
                    foreach ($enrollments as $enrollment) {
                        $updateData = [];

                        // Only set imported fields if they're not already set
                        if (!$enrollment->imported_name && $user->name) {
                            $updateData['imported_name'] = $user->name;
                        }
                        if (!$enrollment->imported_number && $student->student_id_number) {
                            $updateData['imported_number'] = $student->student_id_number;
                        }

                        if (!empty($updateData)) {
                            $enrollment->update($updateData);
                        }

                        // Set student_id to null since we're deleting the student
                        $enrollment->update(['student_id' => null]);
                    }

                    // 2. Delete report cards (cascade handles report_card_details)
                    $student->reportCards()->delete();

                    // 3. Delete transcripts (cascade handles transcript_details)
                    $student->transcripts()->delete();

                    // 4. Delete class histories
                    $student->classHistories()->delete();

                    // 5. Delete the student record
                    $student->delete();
                }

                // 6. Delete the user
                $user->delete();

                DB::commit();
                $this->line("Cleaned up: User #{$user->id} <{$user->email}>" . ($student ? " + Student #{$student->id}" : ''));
                $deleted++;
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Failed: User #{$user->id} <{$user->email}>: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->info("Successfully cleaned up {$deleted} imported student record(s).");
        if ($errors > 0) {
            $this->warn("{$errors} record(s) could not be deleted due to errors.");
        }

        return Command::SUCCESS;
    }
}

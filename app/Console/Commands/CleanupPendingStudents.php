<?php

namespace App\Console\Commands;

use App\Models\ReportCard;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\StudentSubjectEnrollment;
use App\Models\Transcript;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupPendingStudents extends Command
{
    protected $signature = 'students:cleanup-pending {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Delete all students and users created with pending_student_*@example.com email addresses (orphaned records from the ScoreSheet)';

    public function handle(): int
    {
        $pattern = 'pending_student_%@example.com';
        $users = User::where('email', 'like', $pattern)->get();

        if ($users->isEmpty()) {
            $this->info('No pending_student records found. Database is clean!');
            return Command::SUCCESS;
        }

        $this->warn("Found {$users->count()} pending student user(s) to clean up.");

        $dryRun = $this->option('dry-run');
        $totalDeleted = 0;
        $totalErrors = 0;

        foreach ($users as $user) {
            $student = Student::where('user_id', $user->id)->first();

            if (!$student) {
                if ($dryRun) {
                    $this->line("[DRY-RUN] Would delete orphaned user #{$user->id} <{$user->email}>");
                } else {
                    try {
                        $user->delete();
                        $this->line("Deleted orphaned user #{$user->id} <{$user->email}>");
                        $totalDeleted++;
                    } catch (\Exception $e) {
                        $this->error("Failed to delete user #{$user->id}: {$e->getMessage()}");
                        $totalErrors++;
                    }
                }
                continue;
            }

            if ($dryRun) {
                $enrollmentCount = StudentSubjectEnrollment::where('student_id', $student->id)->count();
                $rcCount = ReportCard::where('student_id', $student->id)->count();
                $tCount = Transcript::where('student_id', $student->id)->count();
                $this->line("[DRY-RUN] Would delete student #{$student->id} (user: #{$user->id}, email: {$user->email}) — {$enrollmentCount} enrollment(s), {$rcCount} report card(s), {$tCount} transcript(s)");
                $totalDeleted++;
            } else {
                DB::beginTransaction();
                try {
                    // Remove FK references first so class_histories can be deleted
                    StudentSubjectEnrollment::where('student_id', $student->id)
                        ->update(['student_class_history_id' => null]);

                    // Delete scores + details + enrollments
                    $enrollments = StudentSubjectEnrollment::where('student_id', $student->id)->get();
                    foreach ($enrollments as $enrollment) {
                        if ($enrollment->score) {
                            $enrollment->score->details()->delete();
                            $enrollment->score->delete();
                        }
                        $enrollment->delete();
                    }

                    // Delete class histories
                    StudentClassHistory::where('student_id', $student->id)->delete();

                    // Delete report cards (cascadeOnDelete handles report_card_details)
                    ReportCard::where('student_id', $student->id)->delete();

                    // Delete transcripts (cascadeOnDelete handles transcript_details)
                    Transcript::where('student_id', $student->id)->delete();

                    // Delete the student
                    $student->delete();

                    // Delete the associated user
                    $user->delete();

                    DB::commit();
                    $this->line("Deleted student #{$student->id} and user #{$user->id} <{$user->email}>");
                    $totalDeleted++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("Failed to delete student #{$student->id}: {$e->getMessage()}");
                    $totalErrors++;
                }
            }
        }

        if ($dryRun) {
            $this->warn("[DRY-RUN] Would delete {$totalDeleted} record(s). Run without --dry-run to execute.");
        } else {
            $this->info("Successfully cleaned up {$totalDeleted} pending student record(s).");
            if ($totalErrors > 0) {
                $this->warn("{$totalErrors} record(s) could not be deleted due to errors.");
            }
        }

        return Command::SUCCESS;
    }
}

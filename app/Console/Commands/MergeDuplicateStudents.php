<?php

namespace App\Console\Commands;

use App\Models\Score;
use App\Models\ScoreDetail;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\StudentSubjectEnrollment;
use App\Models\SubjectOffering;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MergeDuplicateStudents extends Command
{
    protected $signature = 'students:merge-duplicates
        {--dry-run : Show what would be merged without actually changing anything}
        {--subject-id= : Only merge duplicates within a specific subject}
        {--term-id= : Only merge duplicates within a specific term (requires --subject-id)}';

    protected $description = 'Find and merge duplicate student records that have the same name across multiple subjects/terms';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $subjectId = $this->option('subject-id');
        $termId = $this->option('term-id');

        // Build the base query for students with a name and active enrollments
        $query = Student::with('user')
            ->whereHas('user', fn ($q) => $q->where('name', '!=', ''))
            ->whereHas('enrollments', fn ($e) => $e->where('status', 'enrolled'));

        if ($subjectId) {
            $offeringQuery = SubjectOffering::where('subject_id', $subjectId)->where('status', 'active');
            if ($termId) {
                $offeringQuery->where('term_id', $termId);
            }
            $offeringIds = $offeringQuery->pluck('id');
            $query->whereHas('enrollments', fn ($e) => $e->whereIn('subject_offering_id', $offeringIds));
        }

        $students = $query->get();

        if ($students->isEmpty()) {
            $this->info('No students found to check for duplicates.');
            return Command::SUCCESS;
        }

        // Group by name to find duplicates — normalized (trim + lowercase) so whitespace/case
        // differences from manual entry or import don't hide an otherwise-obvious duplicate.
        $groups = collect();
        foreach ($students as $student) {
            $name = $student->user?->name ?? '';
            if (trim($name) === '') {
                continue;
            }
            $groups->push(['name' => $name, 'key' => mb_strtolower(trim($name)), 'student' => $student]);
        }
        $groups = $groups->groupBy('key')->filter(fn ($g) => $g->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('No duplicate student names found. All student names are unique!');
            return Command::SUCCESS;
        }

        $this->warn('Found ' . $groups->count() . ' duplicate name group(s):');
        $this->newLine();

        $totalMerged = 0;

        foreach ($groups as $duplicates) {
            $entries = $duplicates->pluck('student');
            $displayName = $duplicates->first()['name'];
            $this->line("  <options=bold>{$displayName}</> - {$entries->count()} duplicate records");

            // Pick the best record to keep:
            // 1. Prefer one with a student_id_number (non-null)
            // 2. Then prefer with most enrollments
            // 3. Otherwise keep the oldest (lowest ID)
            $winner = $entries->sort(function ($a, $b) {
                $aHasId = $a->student_id_number !== null ? 1 : 0;
                $bHasId = $b->student_id_number !== null ? 1 : 0;
                if ($aHasId !== $bHasId) {
                    return $bHasId - $aHasId;
                }
                $aEnr = StudentSubjectEnrollment::where('student_id', $a->id)->count();
                $bEnr = StudentSubjectEnrollment::where('student_id', $b->id)->count();
                if ($aEnr !== $bEnr) {
                    return $bEnr - $aEnr;
                }
                return $a->id - $b->id;
            })->first();

            $duplicatesToMerge = $entries->reject(fn ($s) => $s->id === $winner->id);

            $this->line("    Keeping: #{$winner->id} ({$winner->user->name}, ID#: " . ($winner->student_id_number ?? 'null') . ')');

            foreach ($duplicatesToMerge as $dup) {
                $dupEnrollmentCount = StudentSubjectEnrollment::where('student_id', $dup->id)->count();
                $this->line("    Merging: #{$dup->id} ({$dup->user->name}, ID#: " . ($dup->student_id_number ?? 'null') . ") - {$dupEnrollmentCount} enrollment(s)");

                if ($dryRun) {
                    continue;
                }

                DB::beginTransaction();
                try {
                    // Capture data before deletion
                    $dupUserId = $dup->user_id;
                    $dupIsPlaceholder = $dup->is_placeholder;
                    $dupClassHistories = $dup->classHistories()->pluck('id');

                    // Process each enrollment of the duplicate individually
                    $dupEnrollments = StudentSubjectEnrollment::with('score.details')
                        ->where('student_id', $dup->id)
                        ->get();

                    foreach ($dupEnrollments as $enr) {
                        $offeringId = $enr->subject_offering_id;

                        // Check if the winner already has an enrollment in this same offering
                        $existingEnr = StudentSubjectEnrollment::where('student_id', $winner->id)
                            ->where('subject_offering_id', $offeringId)
                            ->where('status', 'enrolled')
                            ->first();

                        if ($existingEnr) {
                            // Winner already has this subject — merge scores instead
                            if ($enr->score && $existingEnr->score) {
                                // Move any score details from the duplicate to the winner
                                ScoreDetail::where('score_id', $enr->score->id)
                                    ->update(['score_id' => $existingEnr->score->id]);
                            }

                            // Delete the duplicate's now-empty score + enrollment
                            if ($enr->score) {
                                $enr->score->details()->delete();
                                $enr->score->delete();
                            }
                            $enr->delete();
                        } else {
                            // Winner doesn't have this subject — reassign the enrollment
                            $enr->update(['student_id' => $winner->id]);

                            // Try to find a matching class history for the winner
                            $offering = SubjectOffering::find($offeringId);
                            if ($offering) {
                                $matchingHistory = StudentClassHistory::where('student_id', $winner->id)
                                    ->where('class_id', $offering->class_id)
                                    ->where('status', 'active')
                                    ->first();

                                if ($matchingHistory) {
                                    $enr->update(['student_class_history_id' => $matchingHistory->id]);
                                } else {
                                    // No matching history — create one for the winner so the
                                    // enrollment can reference it (column is NOT NULL)
                                    $newHistory = StudentClassHistory::create([
                                        'student_id' => $winner->id,
                                        'class_id' => $offering->class_id,
                                        // subject_offerings has no generation_id column of its own
                                        // (SubjectOffering::generation() is a dead relation) — the
                                        // real path to an offering's generation is via its class.
                                        'generation_id' => $offering->class?->generation_id,
                                        'start_date' => now(),
                                        'status' => 'active',
                                    ]);
                                    $enr->update(['student_class_history_id' => $newHistory->id]);
                                }
                            }
                        }
                    }

                    // Delete duplicate's class histories (enrollments no longer reference them)
                    StudentClassHistory::whereIn('id', $dupClassHistories)->delete();

                    // Delete report cards & transcripts
                    $dup->reportCards()->delete();
                    $dup->transcripts()->delete();

                    // Delete the duplicate student record
                    $dup->delete();

                    // Delete the associated user if it's a placeholder with no other students
                    if ($dupIsPlaceholder) {
                        $otherStudent = Student::where('user_id', $dupUserId)->exists();
                        if (!$otherStudent) {
                            User::where('id', $dupUserId)->delete();
                        }
                    }

                    DB::commit();
                    $totalMerged++;
                    $this->line("      -> Merged into #{$winner->id}");
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("      -> Failed: {$e->getMessage()}");
                }
            }
            $this->newLine();
        }

        if ($dryRun) {
            $this->warn('[DRY-RUN] Would merge ' . $totalMerged . ' duplicate group(s). Run without --dry-run to execute.');
        } else {
            // Invalidate spreadsheet caches so the student page count refreshes
            $this->invalidateSpreadsheetCaches();
            $this->info('Done! Merged ' . $totalMerged . ' duplicate group(s) into their canonical records.');
            $this->info('Spreadsheet caches invalidated — counts should now be accurate.');
        }

        return Command::SUCCESS;
    }

    /**
     * Invalidate all spreadsheet caches so refreshed counts reflect the merge.
     */
    private function invalidateSpreadsheetCaches(): void
    {
        $offerings = SubjectOffering::select('subject_id', 'term_id')->distinct()->get();
        foreach ($offerings as $o) {
            Cache::forget("spreadsheet_{$o->subject_id}_{$o->term_id}");
        }
    }
}

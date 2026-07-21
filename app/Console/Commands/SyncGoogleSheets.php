<?php

namespace App\Console\Commands;

use App\Models\Score;
use App\Models\ScoreDetail;
use App\Models\SubjectOffering;
use App\Models\StudentSubjectEnrollment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncGoogleSheets extends Command
{
    protected $signature = 'google-sheets:sync 
        {--subject= : Subject ID to sync (optional, syncs all if omitted)}
        {--term= : Term ID to sync (optional, syncs all if omitted)}
        {--spreadsheet= : Spreadsheet ID to sync from (optional, uses user\'s last used sheet)}
        {--user= : User ID whose Google credentials to use}';

    protected $description = 'Sync scores from Google Sheets back to the system. Run this as a cron job for auto-sync.';

    public function handle(): int
    {
        $this->info('Starting Google Sheets sync...');

        $userId = $this->option('user');
        $subjectId = $this->option('subject');
        $termId = $this->option('term');
        $spreadsheetId = $this->option('spreadsheet');

        // Find users with Google refresh tokens
        $users = User::whereNotNull('google_refresh_token')->get();

        if ($users->isEmpty()) {
            $this->warn('No users have connected their Google accounts. Skipping sync.');
            return Command::SUCCESS;
        }

        if ($userId) {
            $users = $users->where('id', $userId);
            if ($users->isEmpty()) {
                $this->error('User not found or has no Google credentials.');
                return Command::FAILURE;
            }
        }

        $syncedCount = 0;
        $errorCount = 0;

        foreach ($users as $user) {
            $this->newLine();
            $this->info("Processing user: {$user->name} (ID: {$user->id})");

            try {
                // Refresh the access token
                $accessToken = $this->refreshAccessToken($user);
                if (!$accessToken) {
                    $this->warn("  Cannot refresh token for user {$user->id}. Skipping.");
                    $errorCount++;
                    continue;
                }

                // Build query for offerings
                $query = SubjectOffering::query()->where('status', 'active');

                if ($subjectId) {
                    $query->where('subject_id', $subjectId);
                }
                if ($termId) {
                    $query->where('term_id', $termId);
                }

                $offerings = $query->get();

                if ($offerings->isEmpty()) {
                    $this->warn('  No active offerings found matching criteria.');
                    continue;
                }

                // Group offerings by (subject_id, term_id) to create unique sheet tabs
                $groups = $offerings->groupBy(fn($o) => $o->subject_id . '-' . $o->term_id);

                foreach ($groups as $groupKey => $groupOfferings) {
                    $subject = $groupOfferings->first()->subject;
                    $term = $groupOfferings->first()->term;

                    if (!$subject || !$term) continue;

                    $this->info("  Syncing: {$subject->subject_code} - {$term->name}");

                    // We need a spreadsheet ID - if not provided, try to use stored settings
                    // or skip (user needs to create a sheet first)
                    if (!$spreadsheetId) {
                        $this->warn("    No spreadsheet ID provided. Use --spreadsheet option.");
                        $this->warn("    Create a spreadsheet first via the Google Sheets modal in the app.");
                        continue;
                    }

                    // Determine the sheet tab name
                    $sheetTabTitle = preg_replace('/[\[\]:?*\/\\\\]/', '-', "{$subject->subject_code} - {$term->name}");
                    $sheetTabTitle = mb_substr($sheetTabTitle, 0, 100);

                    // Fetch data from Google Sheets
                    try {
                        $response = Http::withToken($accessToken)
                            ->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . rawurlencode($sheetTabTitle));

                        if (!$response->successful()) {
                            $this->warn("    Sheet tab '{$sheetTabTitle}' not found. Skipping.");
                            continue;
                        }

                        $values = $response->json('values', []);
                        if (count($values) < 2) {
                            $this->warn("    No data found in sheet tab. Skipping.");
                            continue;
                        }

                        $headers = $values[0];
                        $rows = array_slice($values, 1);

                        // Parse column headers
                        $csvColumnMap = [];
                        for ($h = 3; $h < count($headers); $h++) {
                            $colHeader = trim($headers[$h] ?? '');
                            if (preg_match('/^(.+)\s*\(([^)]+)\)$/', $colHeader, $matches)) {
                                $csvColumnMap[$h] = [
                                    'label' => trim($matches[1]),
                                    'type' => strtolower(trim($matches[2])),
                                ];
                            }
                        }

                        if (empty($csvColumnMap)) {
                            $this->warn("    No score columns found in sheet. Skipping.");
                            continue;
                        }

                        // Import data
                        $offeringIds = $groupOfferings->pluck('id');

                        DB::beginTransaction();
                        try {
                            $importedCount = 0;
                            foreach ($rows as $row) {
                                if (count($row) < 2) continue;

                                $studentNumber = trim($row[2] ?? '');
                                $studentName = trim($row[1] ?? '');
                                if (!$studentNumber && !$studentName) continue;

                                // Find enrollment
                                $enrollment = null;
                                if ($studentNumber) {
                                    $enrollment = StudentSubjectEnrollment::whereIn('subject_offering_id', $offeringIds)
                                        ->whereHas('student', fn($q) => $q->where('student_id_number', $studentNumber))
                                        ->first();
                                }

                                if (!$enrollment && $studentName) {
                                    $enrollment = StudentSubjectEnrollment::whereIn('subject_offering_id', $offeringIds)
                                        ->whereHas('student.user', fn($q) => $q->where('name', $studentName))
                                        ->first();
                                }

                                if (!$enrollment) continue;

                                // Ensure score exists
                                if (!$enrollment->score) {
                                    $score = Score::create(['student_subject_enrollment_id' => $enrollment->id]);
                                } else {
                                    $score = $enrollment->score;
                                }

                                // Build detail lookup
                                $detailMap = [];
                                $existingDetails = ScoreDetail::with('assessmentType')
                                    ->where('score_id', $score->id)
                                    ->get();

                                foreach ($existingDetails as $d) {
                                    $key = $d->label . '_' . ($d->assessmentType?->code ?? 'unknown');
                                    $detailMap[$key] = $d;
                                }

                                // Update marks from sheet
                                foreach ($csvColumnMap as $csvIdx => $colInfo) {
                                    if (!isset($row[$csvIdx]) || trim($row[$csvIdx]) === '') continue;

                                    $lookupKey = $colInfo['label'] . '_' . $colInfo['type'];
                                    $detail = $detailMap[$lookupKey] ?? null;

                                    if ($detail) {
                                        $mark = (float) trim($row[$csvIdx]);
                                        if ($mark >= 0 && $mark <= 100) {
                                            $detail->update(['score' => $mark]);
                                        }
                                    }
                                }

                                $this->recalculateTotal($score->id);
                                $importedCount++;
                            }

                            DB::commit();
                            $this->info("    Imported {$importedCount} student scores.");
                            $syncedCount += $importedCount;

                        } catch (\Exception $e) {
                            DB::rollBack();
                            throw $e;
                        }

                    } catch (\Exception $e) {
                        $this->error("    Failed to sync: " . $e->getMessage());
                        Log::error('Google Sheets auto-sync failed', [
                            'user_id' => $user->id,
                            'subject' => $subject->subject_code,
                            'term' => $term->name,
                            'error' => $e->getMessage(),
                        ]);
                        $errorCount++;
                    }
                }

            } catch (\Exception $e) {
                $this->error("  Error processing user {$user->id}: " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->newLine();
        $this->info("Sync complete. Synced: {$syncedCount} scores. Errors: {$errorCount}.");

        return Command::SUCCESS;
    }

    /**
     * Refresh the Google access token for a user.
     */
    private function refreshAccessToken(User $user): ?string
    {
        if (!$user->google_refresh_token) {
            return null;
        }

        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');

        if (!$clientId || !$clientSecret) {
            return null;
        }

        try {
            $response = Http::post('https://oauth2.googleapis.com/token', [
                'refresh_token' => $user->google_refresh_token,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'refresh_token',
            ]);

            if (!$response->successful()) {
                Log::error('Google token refresh failed in sync command', [
                    'user_id' => $user->id,
                    'response' => $response->body(),
                ]);
                return null;
            }

            $tokens = $response->json();

            // Update stored tokens
            $user->google_token_expires_at = now()->addSeconds((int) ($tokens['expires_in'] ?? 3600));
            if (!empty($tokens['refresh_token'])) {
                $user->google_refresh_token = $tokens['refresh_token'];
            }
            $user->save();

            return $tokens['access_token'];

        } catch (\Exception $e) {
            Log::error('Google token refresh exception in sync command', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function recalculateTotal(?int $scoreId): void
    {
        if (!$scoreId) return;
        $score = Score::find($scoreId);
        if (!$score) return;

        $details = ScoreDetail::with('assessmentType')
            ->where('score_id', $scoreId)
            ->whereNotNull('score')
            ->get();

        if ($details->isEmpty()) {
            $score->update(['total' => null, 'grade' => null]);
            return;
        }

        $total = round($details
            ->groupBy(fn($d) => $d->assessmentType?->code ?? 'unknown')
            ->sum(function ($group) {
                $assessmentType = $group->first()->assessmentType;
                if (!$assessmentType) return 0;
                $average = $this->calculateSimpleAverage($group);
                return (($average ?? 0) * ((float) $assessmentType->weight_percent / 100));
            }), 2);

        $grade = \App\Models\GradeBoundary::getGrade($total) ?? 'F';
        $score->update(['total' => $total, 'grade' => $grade]);
    }

    private function calculateSimpleAverage($details): ?float
    {
        $details = $details->filter(fn($d) => $d->mark !== null);
        if ($details->isEmpty()) return null;
        $totalMarks = $details->sum('mark');
        $totalMaxScores = $details->filter(fn($d) => $d->max_score)->sum('max_score');
        if ($totalMaxScores > 0) return ($totalMarks / $totalMaxScores) * 100;
        return $details->avg('mark');
    }
}

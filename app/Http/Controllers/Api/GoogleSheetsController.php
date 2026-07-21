<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradeBoundary;
use App\Models\Score;
use App\Models\ScoreDetail;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleSheetsController extends Controller
{
    /**
     * GET /google-sheets/config
     * Return the Google OAuth configuration for the frontend.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'client_id' => config('services.google.client_id'),
                'scopes' => [
                    'https://www.googleapis.com/auth/spreadsheets',
                    'https://www.googleapis.com/auth/drive.file',
                ],
            ],
        ]);
    }

    /**
     * POST /google-sheets/token
     * Exchange the OAuth authorization code for tokens and store the refresh token.
     */
    public function exchangeToken(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri = config('services.google.redirect_uri', url('/api/google-sheets/callback'));

        if (!$clientId || !$clientSecret) {
            return response()->json(['message' => 'Google OAuth is not configured on the server.'], 500);
        }

        try {
            $response = Http::post('https://oauth2.googleapis.com/token', [
                'code' => $request->code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]);

            if (!$response->successful()) {
                Log::error('Google token exchange failed', ['response' => $response->body()]);
                return response()->json(['message' => 'Failed to exchange authorization code.'], 500);
            }

            $tokens = $response->json();

            // Store tokens on the authenticated user
            /** @var User $user */
            $user = $request->user();
            // Only overwrite the refresh token if a new one is returned.
            // Google only returns refresh_token on the FIRST authorization per scope set.
            if (!empty($tokens['refresh_token'])) {
                $user->google_refresh_token = $tokens['refresh_token'];
            }
            $user->google_token_expires_at = $tokens['expires_in'] 
                ? now()->addSeconds((int) $tokens['expires_in']) 
                : null;
            $user->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'access_token' => $tokens['access_token'],
                    'expires_in' => $tokens['expires_in'] ?? 3600,
                    'has_refresh_token' => !empty($tokens['refresh_token']),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Google token exchange exception', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to exchange authorization code: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /google-sheets/refresh
     * Refresh the Google access token using the stored refresh token.
     */
    public function refreshToken(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->google_refresh_token) {
            return response()->json(['message' => 'No refresh token available. Please reconnect your Google account.'], 400);
        }

        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');

        try {
            $response = Http::post('https://oauth2.googleapis.com/token', [
                'refresh_token' => $user->google_refresh_token,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'refresh_token',
            ]);

            if (!$response->successful()) {
                Log::error('Google token refresh failed', ['response' => $response->body()]);
                return response()->json(['message' => 'Failed to refresh token. Please reconnect your Google account.'], 500);
            }

            $tokens = $response->json();

            // Update the stored tokens
            $user->google_token_expires_at = now()->addSeconds((int) ($tokens['expires_in'] ?? 3600));
            // Some refreshes also return a new refresh_token (rotating tokens)
            if (!empty($tokens['refresh_token'])) {
                $user->google_refresh_token = $tokens['refresh_token'];
            }
            $user->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'access_token' => $tokens['access_token'],
                    'expires_in' => $tokens['expires_in'] ?? 3600,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to refresh token: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /google-sheets/status
     * Check if the user has a connected Google account.
     */
    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'connected' => !empty($user->google_refresh_token),
                'has_valid_token' => $user->google_token_expires_at && $user->google_token_expires_at->isFuture(),
            ],
        ]);
    }

    /**
     * POST /google-sheets/disconnect
     * Disconnect the Google account by removing stored tokens.
     */
    public function disconnect(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->google_refresh_token = null;
        $user->google_token_expires_at = null;
        $user->save();

        return response()->json(['success' => true, 'message' => 'Google account disconnected.']);
    }

    /**
     * POST /google-sheets/create
     * Create a new Google Sheet with "PNC Student Score Management System" title
     * and populate it with all student score data for the given subject & term.
     */
    public function createSheet(Request $request): JsonResponse
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'term_id' => 'required|exists:terms,id',
            'access_token' => 'nullable|string',
        ]);

        $subject = Subject::findOrFail($request->subject_id);
        $term = Term::findOrFail($request->term_id);

        // Try to get a fresh access token from the user's stored refresh token
        $accessToken = $this->resolveAccessToken($request);
        if (!$accessToken) {
            return response()->json([
                'message' => 'No Google access token available. Please connect your Google account first.',
            ], 400);
        }

        $offeringIds = SubjectOffering::where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->pluck('id');

        $enrollments = StudentSubjectEnrollment::with([
            'student.user',
            'subjectOffering.class',
            'score.details.assessmentType',
        ])->whereIn('subject_offering_id', $offeringIds)->get();

        try {
            // Create the main workbook with the system title
            $sheetTabTitle = $this->sanitizeSheetTitle("{$subject->subject_code} - {$term->name}");
            $createResponse = Http::withToken($accessToken)
                ->post('https://sheets.googleapis.com/v4/spreadsheets', [
                    'properties' => ['title' => 'PNC Student Score Management System'],
                    'sheets' => [['properties' => ['title' => $sheetTabTitle, 'index' => 0]]],
                ]);

            if (!$createResponse->successful()) {
                Log::error('Google Sheets create failed', ['status' => $createResponse->status(), 'response' => $createResponse->body()]);
                if ($createResponse->status() === 401) {
                    return response()->json(['message' => 'Google token expired. Please re-connect your Google account.'], 400);
                }
                return response()->json(['message' => 'Failed to create Google Sheet. Check your Google account permissions.'], 500);
            }

            $spreadsheet = $createResponse->json();
            $spreadsheetId = $spreadsheet['spreadsheetId'];

            // Build the values array directly (more reliable than CSV encoding)
            $values = $this->buildValuesArray($enrollments, $subject, $term);

            // Append values to the sheet
            $appendUrl = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . rawurlencode($sheetTabTitle) . ":append?valueInputOption=RAW&insertDataOption=INSERT_ROWS";

            $uploadResponse = Http::withToken($accessToken)
                ->post($appendUrl, ['values' => $values]);

            if (!$uploadResponse->successful()) {
                Log::error('Google Sheets append failed', ['status' => $uploadResponse->status(), 'body' => $uploadResponse->body()]);
                if ($uploadResponse->status() === 401) {
                    return response()->json(['message' => 'Google token expired. Please re-connect your Google account.'], 400);
                }
                return response()->json(['message' => 'Failed to upload data to Google Sheet'], 500);
            }

            // Format the sheet with bold headers, frozen row, auto-resize, and data validation
            $this->formatSheet($accessToken, $spreadsheetId, 0);

            return response()->json([
                'success' => true,
                'data' => [
                    'spreadsheet_id' => $spreadsheetId,
                    'url' => $spreadsheet['spreadsheetUrl'],
                    'name' => 'PNC Student Score Management System',
                    'sheet_tab' => $sheetTabTitle,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Google Sheets create exception', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create Google Sheet: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /google-sheets/import
     * Import data from Google Sheet back to the system.
     * Reads the sheet tab matching subject_code - term name and updates scores.
     */
    public function importSheet(Request $request): JsonResponse
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'term_id' => 'required|exists:terms,id',
            'spreadsheet_id' => 'required|string',
            'access_token' => 'nullable|string',
        ]);

        $subject = Subject::findOrFail($request->subject_id);
        $term = Term::findOrFail($request->term_id);

        // Try to get a fresh access token from the user's stored refresh token
        $accessToken = $this->resolveAccessToken($request);
        if (!$accessToken) {
            return response()->json([
                'message' => 'No Google access token available. Please connect your Google account first.',
            ], 400);
        }

        $spreadsheetId = $request->spreadsheet_id;

        // Find the correct sheet tab
        $sheetTabTitle = $this->sanitizeSheetTitle("{$subject->subject_code} - {$term->name}");

        try {
            $response = Http::withToken($accessToken)
                ->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . rawurlencode($sheetTabTitle));

            if (!$response->successful()) {
                if ($response->status() === 401) {
                    Log::error('Google Sheets import auth failed', ['status' => 401]);
                    return response()->json(['message' => 'Google token expired. Please re-connect your Google account.'], 400);
                }
                // Try to get all sheets and list available tabs
                $sheetMetaResponse = Http::withToken($accessToken)
                    ->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}?fields=sheets.properties");
                
                if ($sheetMetaResponse->successful()) {
                    $sheets = $sheetMetaResponse->json('sheets', []);
                    $sheetNames = array_map(fn($s) => $s['properties']['title'], $sheets);
                    return response()->json([
                        'message' => 'Sheet tab not found. Available tabs: ' . implode(', ', $sheetNames),
                    ], 400);
                }

                return response()->json(['message' => 'Failed to fetch sheet data'], 500);
            }

            $values = $response->json('values', []);
            if (count($values) < 2) {
                return response()->json(['message' => 'No data found in sheet'], 400);
            }

            $headers = $values[0];
            $rows = array_slice($values, 1);

            // Parse headers to understand column structure
            // Expected: #, Student Name, ID, Class, col1 (type1), col2 (type2), ..., Total, Grade
            $csvColumnMap = [];
            for ($h = 2; $h < count($headers); $h++) {
                $colHeader = trim($headers[$h] ?? '');
                // Skip the Class column (index 3) if present
                if (stripos($colHeader, 'class') !== false && $h < 4) continue;
                // Parse "Label (type)" format
                if (preg_match('/^(.+)\s*\(([^)]+)\)$/', $colHeader, $matches)) {
                    $csvColumnMap[$h] = [
                        'label' => trim($matches[1]),
                        'type' => strtolower(trim($matches[2])),
                    ];
                }
            }

            DB::beginTransaction();
            try {
                $offeringIds = SubjectOffering::where('subject_id', $subject->id)
                    ->where('term_id', $term->id)
                    ->pluck('id');

                $importedCount = 0;
                foreach ($rows as $row) {
                    if (count($row) < 2) continue;

                    $studentNumber = trim($row[2] ?? '');
                    $studentName = trim($row[1] ?? '');

                    if (!$studentNumber && !$studentName) continue;

                    // Find enrollment by student number
                    $enrollment = null;
                    if ($studentNumber) {
                        $enrollment = StudentSubjectEnrollment::whereIn('subject_offering_id', $offeringIds)
                            ->whereHas('student', fn($q) => $q->where('student_id_number', $studentNumber))
                            ->first();
                    }

                    // Fallback to name
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

                    // Build lookup of label_type => detail
                    $detailMap = [];
                    $existingDetails = ScoreDetail::with('assessmentType')
                        ->where('score_id', $score->id)
                        ->get();

                    foreach ($existingDetails as $d) {
                        $key = $d->label . '_' . ($d->assessmentType?->code ?? 'unknown');
                        $detailMap[$key] = $d;
                    }

                    // Map CSV columns to ScoreDetails
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
                return response()->json([
                    'success' => true,
                    'message' => "Imported {$importedCount} student scores successfully.",
                    'data' => ['imported_count' => $importedCount],
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Google Sheets import exception', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to import: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Resolve a valid Google access token.
     * First tries the user's stored refresh_token to get a fresh token,
     * falls back to the request's access_token if no refresh token is available.
     */
    private function resolveAccessToken(Request $request): ?string
    {
        /** @var User $user */
        $user = $request->user();

        // If user has a stored refresh token, use it to get a fresh access token
        if ($user && $user->google_refresh_token) {
            $clientId = config('services.google.client_id');
            $clientSecret = config('services.google.client_secret');

            try {
                $response = Http::post('https://oauth2.googleapis.com/token', [
                    'refresh_token' => $user->google_refresh_token,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'refresh_token',
                ]);

                if ($response->successful()) {
                    $tokens = $response->json();
                    // Update the stored token expiry
                    $user->google_token_expires_at = now()->addSeconds((int) ($tokens['expires_in'] ?? 3600));
                    if (!empty($tokens['refresh_token'])) {
                        $user->google_refresh_token = $tokens['refresh_token'];
                    }
                    $user->save();
                    return $tokens['access_token'];
                }

                Log::warning('Google token refresh in resolveAccessToken failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (\Exception $e) {
                Log::error('Google token refresh exception', ['error' => $e->getMessage()]);
            }
        }

        // Fall back to the provided access_token
        return $request->access_token;
    }

    /**
     * Build a 2D values array for Google Sheets with all score data.
     */
    private function buildValuesArray($enrollments, Subject $subject, Term $term): array
    {
        // Build deduplicated columns map
        $columnsMap = collect();
        $enrollments->each(function ($enr) use ($columnsMap) {
            if ($enr->score && $enr->score->details) {
                foreach ($enr->score->details as $d) {
                    $key = $d->label . '_' . ($d->assessmentType?->code ?? 'unknown');
                    if (!$columnsMap->has($key)) {
                        $columnsMap->put($key, [
                            'id' => $d->id,
                            'label' => $d->label,
                            'type' => $d->assessmentType?->code ?? 'unknown',
                            'order_number' => $d->order_number ?? 0,
                        ]);
                    }
                }
            }
        });
        $columns = $columnsMap->values()->sortBy('order_number')->values();

        // Build header row
        $header = ['#', 'Student Name', 'Student ID', 'Class'];
        foreach ($columns as $col) {
            $header[] = "{$col['label']} ({$col['type']})";
        }
        $header[] = 'Total';
        $header[] = 'Grade';

        $values = [$header];

        // Build data rows
        $rowNum = 1;
        foreach ($enrollments as $enr) {
            $name = $enr->student?->user?->name ?? '';
            $studentNum = $enr->student?->student_id_number ?? '';
            $className = $enr->subjectOffering?->class?->name ?? '';

            $row = [$rowNum, $name, $studentNum, $className];

            // Create detail lookup for this student
            $detailMap = [];
            if ($enr->score) {
                foreach ($enr->score->details as $d) {
                    $key = $d->label . '_' . ($d->assessmentType?->code ?? 'unknown');
                    $detailMap[$key] = $d->mark;
                }
            }

            foreach ($columns as $col) {
                $key = $col['label'] . '_' . $col['type'];
                $mark = $detailMap[$key] ?? null;
                $row[] = $mark !== null ? (string) $mark : '';
            }

            $row[] = $enr->score?->total !== null ? (string) $enr->score->total : '';
            $row[] = $enr->score?->grade ?? '';

            $values[] = $row;
            $rowNum++;
        }

        return $values;
    }

    /**
     * Sanitize a string to be a valid Google Sheets tab title.
     * Sheet titles: max 100 chars, cannot contain [ ] : ? * / \
     */
    private function sanitizeSheetTitle(string $title): string
    {
        // Replace characters not allowed in Google Sheets tab titles
        $title = str_replace(['[', ']', ':', '?', '*', '/', '\\'], '-', $title);
        return mb_substr($title, 0, 100);
    }

    /**
     * Format the sheet with bold headers, frozen row, and auto-resize columns.
     */
    private function formatSheet(string $accessToken, string $spreadsheetId, int $sheetId): void
    {
        $requests = [
            // Bold header row
            [
                'repeatCell' => [
                    'range' => ['sheetId' => $sheetId, 'startRowIndex' => 0, 'endRowIndex' => 1],
                    'cell' => [
                        'userEnteredFormat' => [
                            'textFormat' => ['bold' => true, 'fontSize' => 11],
                            'backgroundColor' => ['red' => 0.95, 'green' => 0.95, 'blue' => 0.95],
                        ],
                    ],
                    'fields' => 'userEnteredFormat(textFormat,backgroundColor)',
                ],
            ],
            // Freeze header row
            [
                'updateSheetProperties' => [
                    'properties' => ['sheetId' => $sheetId, 'gridProperties' => ['frozenRowCount' => 1]],
                    'fields' => 'gridProperties.frozenRowCount',
                ],
            ],
            // Auto-resize columns A through the last column
            [
                'autoResizeDimensions' => [
                    'dimensions' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 0,
                        'endIndex' => 10,
                    ],
                ],
            ],
        ];

        Http::withToken($accessToken)
            ->post("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}:batchUpdate", [
                'requests' => $requests,
            ]);
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

        $grade = GradeBoundary::getGrade($total) ?? 'F';
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
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
     * Outgoing HTTP client for Google API calls, pinned to a bundled CA file.
     * php.ini on this machine points curl.cainfo/openssl.cafile at a missing
     * c:\cacert.pem, which breaks TLS verification for every outbound request;
     * we can't write to C:\ here, so the app carries its own CA bundle instead.
     * Falls back to the default verification when the bundled file isn't present
     * (e.g. on a machine where php.ini is configured correctly).
     */
    private function googleHttp(): \Illuminate\Http\Client\PendingRequest
    {
        $bundledCaFile = storage_path('app/certs/cacert.pem');

        return Http::withOptions([
            'verify' => is_file($bundledCaFile) ? $bundledCaFile : true,
        ])
            // The 8s auto-sync poll hits Google's API constantly; transient TCP resets
            // (cURL error 56) happen occasionally and would otherwise surface as a 500
            // and flip the UI to "Reconnect Google" every time. Retry a couple of times
            // before giving up — only for connection-level failures, not HTTP error responses.
            // throw:false is required — retry()'s default (true) makes the client throw on ANY
            // non-2xx final response (not just after an actual retry), which would bypass every
            // $response->successful()/status() check below and turn e.g. a 403 into a raw 500.
            ->retry(2, 300, function (\Exception $e) {
                return $e instanceof \Illuminate\Http\Client\ConnectionException;
            }, throw: false);
    }

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

        if (!$clientId || !$clientSecret) {
            return response()->json(['message' => 'Google OAuth is not configured on the server.'], 500);
        }

        try {
            $response = $this->googleHttp()->post('https://oauth2.googleapis.com/token', [
                'code' => $request->code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                // The frontend uses Google Identity Services' popup code-client flow, which always
                // exchanges the code against the "postmessage" pseudo redirect URI, not a real URL.
                'redirect_uri' => 'postmessage',
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
            $response = $this->googleHttp()->post('https://oauth2.googleapis.com/token', [
                'refresh_token' => $user->google_refresh_token,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'refresh_token',
            ]);

            if (!$response->successful()) {
                Log::error('Google token refresh failed', ['response' => $response->body()]);
                if ($this->isInvalidGrant($response)) {
                    // Per Google's OAuth docs, invalid_grant means the refresh token itself is
                    // dead (revoked, expired, or the account was disabled) — refreshing it will
                    // never succeed again. Clear it so status() stops reporting "connected" and
                    // nothing keeps retrying a token that can never work.
                    $user->google_refresh_token = null;
                    $user->google_token_expires_at = null;
                    $user->save();
                }
                return response()->json(['message' => 'Failed to refresh token. Please reconnect your Google account.'], 400);
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
            $sheetTabTitle = $this->sanitizeSheetTitle(($subject->subject_code ?: $subject->name) . " - {$term->name}");
            $createResponse = $this->googleHttp()->withToken($accessToken)
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

            $uploadResponse = $this->googleHttp()->withToken($accessToken)
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

            // The spreadsheet is created privately, owned only by whichever Google account
            // did the OAuth connect — nobody else can open the link without this, and Google
            // shows its own "You need access" wall that our app has no control over. Our
            // permission middleware (view-scores/create-scores) already gates who gets to click
            // this button in the first place, so anyone who got this far should be able to
            // actually use the resulting sheet without a second, redundant Google-level request.
            $this->shareSheetWithAnyone($accessToken, $spreadsheetId);

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
     * POST /google-sheets/push
     * Push the current, live app data for a subject+term into an ALREADY-EXISTING
     * spreadsheet, overwriting whatever is currently in the matching tab (creating the
     * tab if it's missing). createSheet() only writes data once, at creation time — every
     * student/score/column added or changed in the app afterward never reached the sheet,
     * so reopening a previously-created sheet kept showing a stale snapshot. The frontend
     * calls this every time "Google Sheets" is clicked on an existing sheet, right before
     * opening it, so what you see in Sheets always matches what's in the app first.
     */
    public function pushSheet(Request $request): JsonResponse
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'term_id' => 'required|exists:terms,id',
            'spreadsheet_id' => 'required|string',
            'access_token' => 'nullable|string',
        ]);

        $subject = Subject::findOrFail($request->subject_id);
        $term = Term::findOrFail($request->term_id);

        $accessToken = $this->resolveAccessToken($request);
        if (!$accessToken) {
            return response()->json([
                'message' => 'No Google access token available. Please connect your Google account first.',
            ], 401);
        }

        $spreadsheetId = $request->spreadsheet_id;
        $sheetTabTitle = $this->sanitizeSheetTitle(($subject->subject_code ?: $subject->name) . " - {$term->name}");

        $offeringIds = SubjectOffering::where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->where('status', 'active')
            ->pluck('id');

        $enrollments = StudentSubjectEnrollment::with([
            'student.user',
            'subjectOffering.class',
            'score.details.assessmentType',
        ])->whereIn('subject_offering_id', $offeringIds)->get();

        $values = $this->buildValuesArray($enrollments, $subject, $term);

        try {
            $metaResponse = $this->googleHttp()->withToken($accessToken)
                ->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}?fields=sheets.properties");

            if (!$metaResponse->successful()) {
                if ($metaResponse->status() === 401) {
                    return response()->json(['message' => 'Google token expired. Please re-connect your Google account.'], 401);
                }
                if (in_array($metaResponse->status(), [403, 404], true)) {
                    Log::warning('Google Sheets push permission denied', ['spreadsheet_id' => $spreadsheetId, 'body' => $metaResponse->body()]);
                    return response()->json([
                        'message' => 'This Google account does not have access to that spreadsheet.',
                    ], 403);
                }
                Log::error('Google Sheets push: failed to read spreadsheet metadata', ['status' => $metaResponse->status(), 'body' => $metaResponse->body()]);
                return response()->json(['message' => 'Failed to push data to Google Sheet.'], 500);
            }

            $sheets = $metaResponse->json('sheets', []);
            $existingSheet = collect($sheets)->first(fn($s) => ($s['properties']['title'] ?? null) === $sheetTabTitle);

            if ($existingSheet) {
                $sheetGridId = $existingSheet['properties']['sheetId'];
                // Clear the tab before rewriting — otherwise a column/row removed in the app
                // since the sheet was last written would leave stale cells behind (append only
                // adds rows, it never removes what's already there).
                $clearResponse = $this->googleHttp()->withToken($accessToken)
                    ->post("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . rawurlencode($sheetTabTitle) . ":clear", (object) []);
                if (!$clearResponse->successful()) {
                    Log::warning('Google Sheets push: clear failed', ['status' => $clearResponse->status(), 'body' => $clearResponse->body()]);
                }
            } else {
                // This spreadsheet exists but doesn't have a tab for this subject/term yet.
                $addResponse = $this->googleHttp()->withToken($accessToken)
                    ->post("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}:batchUpdate", [
                        'requests' => [[
                            'addSheet' => ['properties' => ['title' => $sheetTabTitle, 'index' => count($sheets)]],
                        ]],
                    ]);
                if (!$addResponse->successful()) {
                    Log::error('Google Sheets push: failed to add tab', ['status' => $addResponse->status(), 'body' => $addResponse->body()]);
                    return response()->json(['message' => 'Failed to push data to Google Sheet.'], 500);
                }
                $sheetGridId = $addResponse->json('replies.0.addSheet.properties.sheetId');
            }

            $appendUrl = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . rawurlencode($sheetTabTitle) . ":append?valueInputOption=RAW&insertDataOption=INSERT_ROWS";
            $uploadResponse = $this->googleHttp()->withToken($accessToken)->post($appendUrl, ['values' => $values]);

            if (!$uploadResponse->successful()) {
                Log::error('Google Sheets push: upload failed', ['status' => $uploadResponse->status(), 'body' => $uploadResponse->body()]);
                if ($uploadResponse->status() === 401) {
                    return response()->json(['message' => 'Google token expired. Please re-connect your Google account.'], 401);
                }
                return response()->json(['message' => 'Failed to push data to Google Sheet.'], 500);
            }

            $this->formatSheet($accessToken, $spreadsheetId, $sheetGridId);

            return response()->json([
                'success' => true,
                'data' => ['pushed_rows' => max(0, count($values) - 1)],
            ]);
        } catch (\Exception $e) {
            Log::error('Google Sheets push exception', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to push data: ' . $e->getMessage()], 500);
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
            // No usable credential at all — this is the one case the frontend should treat
            // as "reconnect your Google account", so it gets the auth status code.
            return response()->json([
                'message' => 'No Google access token available. Please connect your Google account first.',
            ], 401);
        }

        $spreadsheetId = $request->spreadsheet_id;

        // Find the correct sheet tab — must build this exactly the same way createSheet()
        // and pushSheet() do (with " - "), or this looks for a tab name that was never
        // actually created and every sync silently no-ops via the "tab not found" branch below.
        $sheetTabTitle = $this->sanitizeSheetTitle(($subject->subject_code ?: $subject->name) . " - {$term->name}");

        try {
            $response = $this->googleHttp()->withToken($accessToken)
                ->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . rawurlencode($sheetTabTitle), [
                    // Google's default (FORMATTED_VALUE) renders numbers the way the sheet's
                    // locale displays them — e.g. "85,5" instead of "85.5" outside en-US, or with
                    // thousands separators. PHP's (float) cast silently truncates at the first
                    // non-numeric char, so that would corrupt imported scores without erroring.
                    // UNFORMATTED_VALUE returns the raw underlying number every time.
                    'valueRenderOption' => 'UNFORMATTED_VALUE',
                ]);

            if (!$response->successful()) {
                if ($response->status() === 401) {
                    Log::error('Google Sheets import auth failed', ['status' => 401]);
                    return response()->json(['message' => 'Google token expired. Please re-connect your Google account.'], 401);
                }

                if ($response->status() === 403) {
                    // The token is valid but the connected Google account has no access to THIS
                    // spreadsheet — typically because it was created under a different Google
                    // account than the one currently connected. Surface as 403 (not a blanket 500)
                    // so the frontend's existing 401/403 handling stops the 8s auto-sync poll and
                    // prompts "Reconnect Google" instead of retrying the same denied request forever.
                    Log::warning('Google Sheets import permission denied', ['spreadsheet_id' => $spreadsheetId, 'body' => $response->body()]);
                    return response()->json([
                        'message' => 'This Google account does not have access to that spreadsheet. Reconnect with the Google account that owns it, or create a new sheet.',
                    ], 403);
                }

                // Try to get all sheets and list available tabs
                $sheetMetaResponse = $this->googleHttp()->withToken($accessToken)
                    ->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}?fields=sheets.properties");

                if ($sheetMetaResponse->successful()) {
                    $sheets = $sheetMetaResponse->json('sheets', []);
                    $sheetNames = array_map(fn($s) => $s['properties']['title'], $sheets);
                    // Not an auth problem, and not a failure the user needs to act on — this
                    // subject/term just doesn't have a matching tab (yet). 200 + synced:false
                    // keeps this out of both the "reconnect" path and the browser's own
                    // network-error logging, since periodic auto-sync hits this constantly.
                    return response()->json([
                        'success' => true,
                        'data' => ['synced' => false],
                        'message' => 'Sheet tab not found. Available tabs: ' . implode(', ', $sheetNames),
                    ]);
                }

                if ($sheetMetaResponse->status() === 401 || $sheetMetaResponse->status() === 403) {
                    Log::warning('Google Sheets import permission denied', ['spreadsheet_id' => $spreadsheetId, 'body' => $sheetMetaResponse->body()]);
                    return response()->json([
                        'message' => 'This Google account does not have access to that spreadsheet. Reconnect with the Google account that owns it, or create a new sheet.',
                    ], 403);
                }

                Log::error('Google Sheets import failed to fetch sheet data', ['status' => $sheetMetaResponse->status(), 'body' => $sheetMetaResponse->body()]);
                return response()->json(['message' => 'Failed to fetch sheet data'], 500);
            }

            $values = $response->json('values', []);
            if (count($values) < 2) {
                // Nothing to sync yet (e.g. no enrollments for this subject/term) — not an error.
                return response()->json([
                    'success' => true,
                    'data' => ['synced' => false],
                    'message' => 'No data found in sheet',
                ]);
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

            // Locate the Total/Grade columns by header name (not a fixed position) so we can
            // write the recalculated values straight back — this is what makes Total/Grade in
            // the actual sheet update themselves after a score edit, the same way they do on
            // the score sheet page, instead of sitting frozen at whatever they were when the
            // sheet was created/last pushed.
            $totalColIdx = null;
            $gradeColIdx = null;
            foreach ($headers as $h => $label) {
                $normalized = strtolower(trim((string) $label));
                if ($normalized === 'total') $totalColIdx = $h;
                if ($normalized === 'grade') $gradeColIdx = $h;
            }

            DB::beginTransaction();
            try {
                $offeringIds = SubjectOffering::where('subject_id', $subject->id)
                    ->where('term_id', $term->id)
                    ->pluck('id');

                $importedCount = 0;
                $sheetWriteBack = [];
                foreach ($rows as $rowIndex => $row) {
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

                    // Sync the Student Name / Student ID cells too — the score sheet page lets
                    // these two be edited inline same as any score cell, so an edit made
                    // directly in Sheets should land back in the app the same way a mark does.
                    // (Only these two — Class isn't editable via import: it's a read-only
                    // projection of the student's actual class assignment, and blindly writing
                    // whatever text is in that cell back as a class change would be unsafe.)
                    if ($studentName && $enrollment->student?->user && $enrollment->student->user->name !== $studentName) {
                        try {
                            $enrollment->student->user->update(['name' => $studentName]);
                        } catch (\Exception $e) {
                            Log::warning('Google Sheets import: failed to update student name', ['enrollment_id' => $enrollment->id, 'error' => $e->getMessage()]);
                        }
                    }
                    if ($studentNumber && $enrollment->student && $enrollment->student->student_id_number !== $studentNumber) {
                        try {
                            $enrollment->student->update(['student_id_number' => $studentNumber]);
                        } catch (\Exception $e) {
                            Log::warning('Google Sheets import: failed to update student ID number', ['enrollment_id' => $enrollment->id, 'error' => $e->getMessage()]);
                        }
                    }

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

                    if ($totalColIdx !== null || $gradeColIdx !== null) {
                        $score->refresh(); // recalculateTotal() updates the DB row via its own model instance
                        $sheetRow = $rowIndex + 2; // +1 for the header row, +1 for 1-indexing
                        if ($totalColIdx !== null) {
                            $sheetWriteBack[] = [
                                'range' => "'{$sheetTabTitle}'!" . $this->columnIndexToLetter($totalColIdx) . $sheetRow,
                                'values' => [[$score->total !== null ? (string) $score->total : '']],
                            ];
                        }
                        if ($gradeColIdx !== null) {
                            $sheetWriteBack[] = [
                                'range' => "'{$sheetTabTitle}'!" . $this->columnIndexToLetter($gradeColIdx) . $sheetRow,
                                'values' => [[$score->grade ?? '']],
                            ];
                        }
                    }
                }

                DB::commit();

                if (!empty($sheetWriteBack)) {
                    // Best-effort — the import into our own DB already succeeded and is what
                    // matters most; a failure writing Total/Grade back to the sheet shouldn't
                    // turn a successful sync into a reported failure.
                    $writeBackResponse = $this->googleHttp()->withToken($accessToken)
                        ->post("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values:batchUpdate", [
                            'valueInputOption' => 'RAW',
                            'data' => $sheetWriteBack,
                        ]);
                    if (!$writeBackResponse->successful()) {
                        Log::warning('Google Sheets Total/Grade write-back failed', [
                            'spreadsheet_id' => $spreadsheetId,
                            'status' => $writeBackResponse->status(),
                            'body' => $writeBackResponse->body(),
                        ]);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => "Imported {$importedCount} student scores successfully.",
                    'data' => ['synced' => true, 'imported_count' => $importedCount],
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
     * POST /google-sheets/ensure-shared
     * Re-applies "anyone with the link can edit" sharing to an already-existing spreadsheet.
     * Sheets created before this sharing feature existed (or ones where the sharing call
     * failed at creation time) are stuck private to whoever originally connected Google —
     * everyone else hits Google's own "You need access" wall when opening the stored link
     * directly, since that link is opened client-side and never touches our backend at all.
     * The frontend calls this right before opening a previously-created sheet so access
     * self-heals automatically instead of requiring a manual localStorage/DevTools fix.
     * Best-effort: failures don't block opening the sheet, they just mean sharing didn't
     * change (e.g. the connected account isn't the owner and can't grant sharing on it).
     */
    public function ensureShared(Request $request): JsonResponse
    {
        $request->validate([
            'spreadsheet_id' => 'required|string',
            'access_token' => 'nullable|string',
        ]);

        $accessToken = $this->resolveAccessToken($request);
        if (!$accessToken) {
            return response()->json(['success' => true, 'data' => ['shared' => false]]);
        }

        $shared = $this->shareSheetWithAnyone($accessToken, $request->spreadsheet_id);

        return response()->json(['success' => true, 'data' => ['shared' => $shared]]);
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
                $response = $this->googleHttp()->post('https://oauth2.googleapis.com/token', [
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

                if ($this->isInvalidGrant($response)) {
                    // Dead refresh token (revoked/expired) — see refreshToken() for why this
                    // must be cleared rather than left to fail the same way on every request.
                    $user->google_refresh_token = null;
                    $user->google_token_expires_at = null;
                    $user->save();
                }
            } catch (\Exception $e) {
                Log::error('Google token refresh exception', ['error' => $e->getMessage()]);
            }
        }

        // Fall back to the provided access_token
        return $request->access_token;
    }

    /**
     * Whether a failed token-endpoint response indicates the refresh token itself is
     * permanently dead (per Google's OAuth docs: revoked, expired, or account disabled),
     * as opposed to a transient failure worth leaving the stored token in place for.
     */
    private function isInvalidGrant(\Illuminate\Http\Client\Response $response): bool
    {
        return $response->json('error') === 'invalid_grant';
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
     * Convert a 0-indexed column number to its spreadsheet column letter(s): 0 -> A, 25 -> Z,
     * 26 -> AA, etc. Used to build A1-notation ranges (e.g. "K5") for writing Total/Grade
     * back to whichever columns they actually landed in for a given sheet's header row.
     */
    private function columnIndexToLetter(int $index): string
    {
        $letter = '';
        $index++; // 1-indexed for this algorithm
        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letter = \chr(65 + $remainder) . $letter;
            $index = intdiv($index - 1, 26);
        }
        return $letter;
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

        $this->googleHttp()->withToken($accessToken)
            ->post("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}:batchUpdate", [
                'requests' => $requests,
            ]);
    }

    /**
     * Share a Drive file (the spreadsheet we just created) with "anyone with the link"
     * as an editor, via the Drive API's permissions.create — the standard Sheets+Drive
     * combo (see https://developers.google.com/drive/api/guides/manage-sharing). Without
     * this the file is private to whichever Google account did the OAuth connect, and
     * everyone else hits Google's own "You need access" wall — a wall our app-level
     * permission middleware (view-scores/create-scores) already made redundant.
     * Best-effort: sheet creation already succeeded, so a sharing failure (e.g. a Workspace
     * domain policy blocking link-sharing) is logged, not surfaced as a failed creation.
     */
    private function shareSheetWithAnyone(string $accessToken, string $spreadsheetId): bool
    {
        $response = $this->googleHttp()->withToken($accessToken)
            ->post("https://www.googleapis.com/drive/v3/files/{$spreadsheetId}/permissions", [
                'type' => 'anyone',
                'role' => 'writer',
            ]);

        if (!$response->successful()) {
            Log::warning('Google Sheets share-with-anyone failed', [
                'spreadsheet_id' => $spreadsheetId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        return true;
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
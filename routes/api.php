<?php

use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AssessmentTypeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChartController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EmailDomainRuleController;
use App\Http\Controllers\Api\GenerationController;
use App\Http\Controllers\Api\GoogleSheetsController;
use App\Http\Controllers\Api\GradeBoundaryController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportCardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StudentPortalController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ScoreController;
use App\Http\Controllers\Api\SpreadsheetController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\SubjectOfferingController;
use App\Http\Controllers\Api\SubjectTermController;
use App\Http\Controllers\Api\TermController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/google-login', [AuthController::class, 'googleLogin']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// These were reachable with zero authentication (no auth:sanctum at all) — same class of
// student/grade data as /dashboard just below, which already requires it.
Route::get('/chart/grade-distribution', [ChartController::class, 'gradeDistribution'])->middleware('auth:sanctum');
Route::get('/chart/subject-performance', [ChartController::class, 'subjectPerformance'])->middleware('auth:sanctum');
Route::get('/chart/summary', [ChartController::class, 'summary'])->middleware('auth:sanctum');
Route::get('/chart/trends', [ChartController::class, 'trends'])->middleware('auth:sanctum');


Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth:sanctum');
Route::get('/dashboard/filters', [DashboardController::class, 'filters'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::patch('/change-password', [AuthController::class, 'changePassword']);

    // ── Profile ──────────────────────────────────────────────────
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);

    // ── Student self-service (role: student only) ──────────────
    Route::middleware('role:student')->group(function () {
        Route::get('/student/portal', [StudentPortalController::class, 'portal']);
        Route::get('/student/scores', [StudentPortalController::class, 'scores']);
        Route::get('/student/transcript', [StudentPortalController::class, 'transcript']);
        Route::get('/student/transcript/download', [StudentPortalController::class, 'transcriptDownload']);
    });

    // ── Users ──────────────────────────────────────────────────────
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:view-users');
    Route::get('/users/roles', [UserController::class, 'roles'])->middleware('permission:view-users');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:view-users');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:create-users');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:update-users');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:delete-users');
    Route::post('/users/bulk-delete', [UserController::class, 'bulkDelete'])->middleware('permission:delete-users');

    // ── Permission & Role Management ──────────────────────────────
    Route::middleware('permission:manage-roles-permissions')->group(function () {
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::get('/roles', [PermissionController::class, 'roles']);
        Route::post('/roles', [PermissionController::class, 'store']);
        Route::put('/roles/{role}', [PermissionController::class, 'update']);
        Route::get('/roles/{role}/permissions', [PermissionController::class, 'rolePermissions']);
        Route::put('/roles/{role}/permissions', [PermissionController::class, 'syncRolePermissions']);
        Route::delete('/roles/{role}', [PermissionController::class, 'destroy']);
        Route::post('/roles/{role}/permissions/{permission}', [PermissionController::class, 'grantPermission']);
        Route::delete('/roles/{role}/permissions/{permission}', [PermissionController::class, 'revokePermission']);
    });

    // ── Active student sign-in domains — used by import flows (any role that can import
    // students/scores, not just admin) to pick which domain new student accounts get ──
    Route::get('/email-domain-rules/student-domains', [EmailDomainRuleController::class, 'studentDomains'])->middleware('permission:create-scores');

    // ── Sign-in domain rules (Google login role assignment) ────────
    Route::get('/email-domain-rules', [EmailDomainRuleController::class, 'index'])->middleware('permission:view-email-domain-rules');
    Route::post('/email-domain-rules', [EmailDomainRuleController::class, 'store'])->middleware('permission:create-email-domain-rules');
    Route::put('/email-domain-rules/{emailDomainRule}', [EmailDomainRuleController::class, 'update'])->middleware('permission:update-email-domain-rules');
    Route::delete('/email-domain-rules/{emailDomainRule}', [EmailDomainRuleController::class, 'destroy'])->middleware('permission:delete-email-domain-rules');

    // ── Activity Logs ────────────────────────────────────────────
    Route::get('/chart/recent-activity', [ChartController::class, 'recentActivity'])->middleware('permission:view-activity-logs');
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->middleware('permission:view-activity-logs');
    Route::post('/activity-logs/bulk-delete', [ActivityLogController::class, 'bulkDestroy'])->middleware('auth:sanctum');

    // ── Students ─────────────────────────────────────────────────
    Route::get('/students', [StudentController::class, 'index'])->middleware('permission:view-students');
    Route::get('/students/{student}', [StudentController::class, 'show'])->middleware('permission:view-students');
    Route::get('/students/{student}/scores', [StudentController::class, 'scores'])->middleware('permission:view-scores');
    Route::post('/students', [StudentController::class, 'store'])->middleware('permission:create-students');
    Route::put('/students/{student}', [StudentController::class, 'update'])->middleware('permission:update-students');
    Route::put('/students/{student}/assign-class', [StudentController::class, 'assignClass'])->middleware('permission:update-students');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->middleware('permission:delete-students');
    Route::post('/students/import', [StudentController::class, 'importBulk'])->middleware('permission:create-students');
    Route::post('/students/bulk-delete', [StudentController::class, 'bulkDelete'])->middleware('permission:delete-students');

    // ── Classes ──────────────────────────────────────────────────
    Route::get('/classes', [ClassController::class, 'index'])->middleware('permission:view-classes');
    Route::post('/classes', [ClassController::class, 'store'])->middleware('permission:create-classes');
    Route::put('/classes/{class}', [ClassController::class, 'update'])->middleware('permission:update-classes');
    Route::delete('/classes/{class}', [ClassController::class, 'destroy'])->middleware('permission:delete-classes');

    // ── Subjects ─────────────────────────────────────────────────
    Route::get('/subjects', [SubjectController::class, 'index'])->middleware('permission:view-subjects');
    Route::get('/subjects/{subject}', [SubjectController::class, 'show'])->middleware('permission:view-subjects');
    Route::post('/subjects', [SubjectController::class, 'store'])->middleware('permission:create-subjects');
    Route::put('/subjects/{subject}', [SubjectController::class, 'update'])->middleware('permission:update-subjects');
    Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])->middleware('permission:delete-subjects');
    Route::get('/teachers', [SubjectController::class, 'teachers'])->middleware('permission:view-teachers');

    Route::get('/academic-years', [AcademicYearController::class, 'index']);

    // ── Subject-Term Assignments (subject_term pivot) ─────────────
    Route::get('/subject-terms', [SubjectTermController::class, 'index'])->middleware('permission:view-subjects');
    Route::post('/subject-terms/sync', [SubjectTermController::class, 'syncBatch'])->middleware('permission:update-subjects');
    Route::put('/subject-terms/{subject}', [SubjectTermController::class, 'syncSubject'])->middleware('permission:update-subjects');

    // ── Subject Offerings ────────────────────────────────────────
    Route::get('/subject-offerings', [SubjectOfferingController::class, 'index'])->middleware('permission:view-subjects');

    // ── Enrollments by offering ──────────────────────────────────
    Route::get('/subject-offerings/{offering}/enrollments', [SubjectOfferingController::class, 'enrollments'])->middleware('permission:view-scores');

    // ── Score by enrollment ──────────────────────────────────────
    Route::get('/scores/by-enrollment/{enrollment}', [ScoreController::class, 'byEnrollment'])->middleware('permission:view-scores');

    // ── Scores ───────────────────────────────────────────────────
    Route::get('/scores', [ScoreController::class, 'index'])->middleware('permission:view-scores');
    Route::get('/scores/{score}', [ScoreController::class, 'show'])->middleware('permission:view-scores');
    Route::post('/scores', [ScoreController::class, 'store'])->middleware('permission:create-scores');
    Route::delete('/scores/{score}', [ScoreController::class, 'destroy'])->middleware('permission:delete-scores');
    Route::post('/scores/{score}/details', [ScoreController::class, 'addDetail'])->middleware('permission:create-scores');
    Route::put('/scores/{score}/details/{detail}', [ScoreController::class, 'updateDetail'])->middleware('permission:update-scores');
    Route::delete('/scores/{score}/details/{detail}', [ScoreController::class, 'deleteDetail'])->middleware('permission:delete-scores');


    // ── Spreadsheet (Score Sheet) ─────────────────────────────────
    Route::get('/spreadsheet/subjects', [SpreadsheetController::class, 'subjects'])->middleware('permission:view-scores');
    Route::get('/spreadsheet/subject/{subject}/term/{term}', [SpreadsheetController::class, 'bySubjectAndTerm'])->middleware('permission:view-scores');
    Route::put('/spreadsheet/subject/{subject}/term/{term}/details/{detail}', [SpreadsheetController::class, 'updateDetail'])->middleware('permission:update-scores');
    Route::patch('/spreadsheet/subject/{subject}/term/{term}/details/{detail}/rename', [SpreadsheetController::class, 'renameDetail'])->middleware('permission:update-scores');
    Route::post('/spreadsheet/subject/{subject}/term/{term}/details', [SpreadsheetController::class, 'addDetail'])->middleware('permission:create-scores');
    Route::delete('/spreadsheet/subject/{subject}/term/{term}/details/{detail}', [SpreadsheetController::class, 'deleteDetail'])->middleware('permission:delete-scores');
    Route::patch('/spreadsheet/subject/{subject}/term/{term}/details/change-type', [SpreadsheetController::class, 'changeColumnType'])->middleware('permission:update-scores');
    Route::post('/spreadsheet/subject/{subject}/term/{term}/reorder', [SpreadsheetController::class, 'reorderColumns'])->middleware('permission:update-scores');
    Route::post('/spreadsheet/subject/{subject}/term/{term}/sync-google', [SpreadsheetController::class, 'syncToGoogleSheets'])->middleware('permission:view-scores');
    Route::post('/spreadsheet/subject/{subject}/term/{term}/import-google', [SpreadsheetController::class, 'importFromGoogleSheets'])->middleware('permission:create-scores');
    Route::post('/spreadsheet/subject/{subject}/term/{term}/import-file', [SpreadsheetController::class, 'importFile'])->middleware('permission:create-scores');
    Route::put('/spreadsheet/weights', [SpreadsheetController::class, 'updateWeights'])->middleware('permission:update-scores');
    Route::get('/spreadsheet/student-numbers', [SpreadsheetController::class, 'studentNumbers'])->middleware('permission:view-scores');
    Route::post('/spreadsheet/subject/{subject}/term/{term}/enrollments', [SpreadsheetController::class, 'addEnrollment'])->middleware('permission:create-scores');
    Route::put('/spreadsheet/subject/{subject}/term/{term}/enrollments/{enrollment}', [SpreadsheetController::class, 'updateEnrollment'])->middleware('permission:update-scores');
    Route::delete('/spreadsheet/subject/{subject}/term/{term}/enrollments/{enrollment}', [SpreadsheetController::class, 'deleteEnrollment'])->middleware('permission:delete-scores');

    // ── Grade Boundaries ─────────────────────────────────────────
    Route::get('/grade-boundaries', [GradeBoundaryController::class, 'index'])->middleware('permission:view-grade-boundaries');
    Route::put('/grade-boundaries/{gradeBoundary}', [GradeBoundaryController::class, 'update'])->middleware('permission:update-grade-boundaries');

    // ── Assessment Types ────────────────────────────────────────────
    Route::get('/assessment-types', [AssessmentTypeController::class, 'index'])->middleware('permission:view-assessment-types');
    Route::post('/assessment-types', [AssessmentTypeController::class, 'store'])->middleware('permission:create-assessment-types');
    Route::put('/assessment-types/{assessmentType}', [AssessmentTypeController::class, 'update'])->middleware('permission:update-assessment-types');
    Route::delete('/assessment-types/{assessmentType}', [AssessmentTypeController::class, 'destroy'])->middleware('permission:delete-assessment-types');

    // ── Terms ────────────────────────────────────────────────────
    Route::get('/terms', [TermController::class, 'index'])->middleware('permission:view-terms');
    Route::post('/terms', [TermController::class, 'store'])->middleware('permission:create-terms');
    Route::put('/terms/{term}', [TermController::class, 'update'])->middleware('permission:update-terms');
    Route::delete('/terms/{term}', [TermController::class, 'destroy'])->middleware('permission:delete-terms');

    // ── Generations ──────────────────────────────────────────────
    Route::get('/generations', [GenerationController::class, 'index'])->middleware('permission:view-generations');
    Route::post('/generations', [GenerationController::class, 'store'])->middleware('permission:create-generations');
    Route::put('/generations/{generation}', [GenerationController::class, 'update'])->middleware('permission:update-generations');
    Route::delete('/generations/{generation}', [GenerationController::class, 'destroy'])->middleware('permission:delete-generations');

    // ── Report Cards ─────────────────────────────────────────────
    Route::get('/report-cards', [ReportCardController::class, 'index'])->middleware('permission:view-report-cards');
    Route::get('/report-cards/{reportCard}', [ReportCardController::class, 'show'])->middleware('permission:view-report-cards');
    Route::post('/subject-offerings/{offering}/generate-report-cards', [ReportCardController::class, 'generateByOffering'])->middleware('permission:generate-report-cards');

    // ── Reports (analytics for the Reports page) ─────────────────
    // Staff-only: the student role also carries view-reports (for its own portal
    // views), and these endpoints expose the whole cohort's scores — so the role
    // gate matches the frontend route meta rather than the permission alone.
    Route::middleware(['role:admin,teacher', 'permission:view-reports'])->prefix('reports')->group(function () {
        Route::get('/filters', [ReportController::class, 'filters']);
        Route::get('/overview', [ReportController::class, 'overview']);
        Route::get('/class-performance', [ReportController::class, 'classPerformance']);
        Route::get('/subject-ranking', [ReportController::class, 'subjectRanking']);
        Route::get('/student-ranking', [ReportController::class, 'studentRanking']);
        Route::get('/students/{student}/report-card', [ReportController::class, 'studentReportCard']);
    });

    // ── Transcripts ───────────────────────────────────────────────
    Route::get('/transcripts', [ReportCardController::class, 'transcriptIndex'])->middleware('permission:view-report-cards');
    Route::post('/students/{student}/generate-transcript', [ReportCardController::class, 'generateTranscript'])->middleware('permission:generate-report-cards');

    // ── Google Sheets OAuth Integration ────────────────────────────
    Route::get('/google-sheets/config', [GoogleSheetsController::class, 'config']);
    Route::get('/google-sheets/status', [GoogleSheetsController::class, 'status']);
    // These three touch the app-wide Google OAuth connection (exchange/refresh/revoke a shared
    // credential) — previously reachable by any authenticated user, including a student. Gated
    // at the same level as the rest of the Google Sheets feature (create/import require
    // view-scores/create-scores) rather than left open.
    Route::post('/google-sheets/token', [GoogleSheetsController::class, 'exchangeToken'])->middleware('permission:create-scores');
    Route::post('/google-sheets/refresh', [GoogleSheetsController::class, 'refreshToken'])->middleware('permission:create-scores');
    Route::post('/google-sheets/disconnect', [GoogleSheetsController::class, 'disconnect'])->middleware('permission:create-scores');
    Route::post('/google-sheets/create', [GoogleSheetsController::class, 'createSheet'])->middleware('permission:view-scores');
    Route::post('/google-sheets/push', [GoogleSheetsController::class, 'pushSheet'])->middleware('permission:view-scores');
    Route::post('/google-sheets/import', [GoogleSheetsController::class, 'importSheet'])->middleware('permission:create-scores');
    Route::post('/google-sheets/ensure-shared', [GoogleSheetsController::class, 'ensureShared'])->middleware('permission:view-scores');
});


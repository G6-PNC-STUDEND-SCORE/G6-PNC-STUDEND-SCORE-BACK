<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChartController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GoogleSheetsController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StudentPortalController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ScoreController;
use App\Http\Controllers\Api\SpreadsheetController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/google-login', [AuthController::class, 'googleLogin']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/chart/grade-distribution', [ChartController::class, 'gradeDistribution']);
Route::get('/chart/subject-performance', [ChartController::class, 'subjectPerformance']);
Route::get('/chart/summary', [ChartController::class, 'summary']);
Route::get('/chart/trends', [ChartController::class, 'trends']);


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

    // ── ADMIN ONLY — Users ───────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/roles', [UserController::class, 'roles']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });

    // ── ADMIN ONLY — Permission & Role Management ────────────────
    Route::middleware('role:admin')->group(function () {
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

    // ── Admin & Teacher — Activity Logs ───────────────────────────
    Route::middleware('role:admin,teacher')->group(function () {
        Route::get('/chart/recent-activity', [ChartController::class, 'recentActivity']);
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    });

    // ── Students ─────────────────────────────────────────────────
    Route::get('/students', [StudentController::class, 'index'])->middleware('permission:view-students');
    Route::get('/students/{student}', [StudentController::class, 'show'])->middleware('permission:view-students');
    Route::get('/students/{student}/scores', [StudentController::class, 'scores'])->middleware('permission:view-scores');
    Route::post('/students', [StudentController::class, 'store'])->middleware('permission:create-students');
    Route::put('/students/{student}', [StudentController::class, 'update'])->middleware('permission:update-students');
    Route::put('/students/{student}/assign-class', [StudentController::class, 'assignClass'])->middleware('permission:update-students');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->middleware('permission:delete-students');
    Route::post('/students/import', [StudentController::class, 'importBulk'])->middleware('permission:create-students');

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

    // ── Generations ──────────────────────────────────────────────
    Route::get('/generations', function () {
        return response()->json([
            'success' => true,
            'data' => \App\Models\Generation::orderBy('year', 'desc')->get(['id', 'year'])->map(fn($g) => [
                'id' => $g->id,
                'year' => $g->year,
                'name' => 'Generation ' . $g->year,
            ]),
        ]);
    });

    // ── Terms ────────────────────────────────────────────────────
    Route::get('/terms', function () {
        return response()->json([
            'success' => true,
            'data' => \App\Models\Term::all(['id', 'name']),
        ]);
    });

    Route::get('/academic-years', function () {
        return response()->json([
            'success' => true,
            'data' => \App\Models\Generation::all(['id', 'name']),
        ]);
    });

    // ── Subject-Term Assignments (subject_term pivot) ─────────────
    Route::get('/subject-terms', [\App\Http\Controllers\Api\SubjectTermController::class, 'index'])->middleware('permission:view-subjects');
    Route::post('/subject-terms/sync', [\App\Http\Controllers\Api\SubjectTermController::class, 'syncBatch'])->middleware('permission:update-subjects');
    Route::put('/subject-terms/{subject}', [\App\Http\Controllers\Api\SubjectTermController::class, 'syncSubject'])->middleware('permission:update-subjects');

    // ── Subject Offerings ────────────────────────────────────────
    Route::get('/subject-offerings', function (\Illuminate\Http\Request $request) {
        $query = \App\Models\SubjectOffering::with(['subject', 'teacher.user', 'class', 'term'])
            ->where('status', 'active');
        if ($request->term_id) $query->where('term_id', $request->term_id);
        if ($request->class_id) $query->where('class_id', $request->class_id);
        if ($request->teacher_id) $query->where('teacher_id', $request->teacher_id);
        return response()->json(['success' => true, 'data' => $query->get()]);
    })->middleware('permission:view-subjects');

    // ── Enrollments by offering ──────────────────────────────────
    Route::get('/subject-offerings/{offering}/enrollments', function (\App\Models\SubjectOffering $offering) {
        $enrollments = \App\Models\StudentSubjectEnrollment::with([
            'student.user',
            'student.studentNumberSequence',
            'score.details.assessmentType',
        ])->where('subject_offering_id', $offering->id)->get();
        return response()->json(['success' => true, 'data' => $enrollments]);
    })->middleware('permission:view-scores');

    // ── Assessment Types ─────────────────────────────────────────
    Route::get('/assessment-types', function () {
        return response()->json([
            'success' => true,
            'data' => \App\Models\AssessmentType::where('is_active', true)->get(),
        ]);
    });

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
    Route::get('/spreadsheet/subjects', [\App\Http\Controllers\Api\SpreadsheetController::class, 'subjects'])->middleware('permission:view-scores');
    Route::get('/spreadsheet/subject/{subject}/term/{term}', [\App\Http\Controllers\Api\SpreadsheetController::class, 'bySubjectAndTerm'])->middleware('permission:view-scores');
    Route::put('/spreadsheet/subject/{subject}/term/{term}/details/{detail}', [\App\Http\Controllers\Api\SpreadsheetController::class, 'updateDetail'])->middleware('permission:update-scores');
    Route::patch('/spreadsheet/subject/{subject}/term/{term}/details/{detail}/rename', [\App\Http\Controllers\Api\SpreadsheetController::class, 'renameDetail'])->middleware('permission:update-scores');
    Route::post('/spreadsheet/subject/{subject}/term/{term}/details', [\App\Http\Controllers\Api\SpreadsheetController::class, 'addDetail'])->middleware('permission:create-scores');
    Route::delete('/spreadsheet/subject/{subject}/term/{term}/details/{detail}', [\App\Http\Controllers\Api\SpreadsheetController::class, 'deleteDetail'])->middleware('permission:delete-scores');
    Route::post('/spreadsheet/subject/{subject}/term/{term}/reorder', [\App\Http\Controllers\Api\SpreadsheetController::class, 'reorderColumns'])->middleware('permission:update-scores');
    Route::post('/spreadsheet/subject/{subject}/term/{term}/sync-google', [\App\Http\Controllers\Api\SpreadsheetController::class, 'syncToGoogleSheets'])->middleware('permission:view-scores');
    Route::post('/spreadsheet/subject/{subject}/term/{term}/import-google', [\App\Http\Controllers\Api\SpreadsheetController::class, 'importFromGoogleSheets'])->middleware('permission:create-scores');
    Route::put('/spreadsheet/weights', [\App\Http\Controllers\Api\SpreadsheetController::class, 'updateWeights'])->middleware('permission:update-scores');

    // ── Google Sheets OAuth Integration ────────────────────────────
    Route::post('/google-sheets/create', [GoogleSheetsController::class, 'createSheet'])->middleware('permission:view-scores');
    Route::post('/google-sheets/import', [GoogleSheetsController::class, 'importSheet'])->middleware('permission:create-scores');
});


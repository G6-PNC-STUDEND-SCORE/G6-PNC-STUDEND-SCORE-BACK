<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChartController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GoogleSheetsController;
use App\Http\Controllers\Api\GradeBoundaryController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportCardController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ScoreController;
use App\Http\Controllers\Api\SpreadsheetController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SubjectController;
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
Route::get('/chart/recent-activity', [ChartController::class, 'recentActivity']);

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

    // ── ADMIN ONLY — Permission & Role Management ────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::get('/roles', [PermissionController::class, 'roles']);
        Route::get('/roles/{role}/permissions', [PermissionController::class, 'rolePermissions']);
        Route::put('/roles/{role}/permissions', [PermissionController::class, 'syncRolePermissions']);
        Route::post('/roles/{role}/permissions/{permission}', [PermissionController::class, 'grantPermission']);
        Route::delete('/roles/{role}/permissions/{permission}', [PermissionController::class, 'revokePermission']);
    });

    // ── Students ─────────────────────────────────────────────────
    Route::get('/students', [StudentController::class, 'index'])->middleware('permission:view-students');
    Route::get('/students/{student}', [StudentController::class, 'show'])->middleware('permission:view-students');
    Route::get('/students/{student}/scores', [StudentController::class, 'scores'])->middleware('permission:view-scores');
    Route::post('/students', [StudentController::class, 'store'])->middleware('permission:create-students');
    Route::put('/students/{student}', [StudentController::class, 'update'])->middleware('permission:update-students');
    Route::put('/students/{student}/assign-class', [StudentController::class, 'assignClass'])->middleware('permission:update-students');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->middleware('permission:delete-students');

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

    // ── Terms ────────────────────────────────────────────────────
    Route::get('/terms', function () {
        return response()->json([
            'success' => true,
            'data' => \App\Models\Term::all(['id', 'name']),
        ]);
    });

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
    Route::get('/spreadsheet/student-numbers', [\App\Http\Controllers\Api\SpreadsheetController::class, 'studentNumbers'])->middleware('permission:view-scores');
    Route::post('/spreadsheet/subject/{subject}/term/{term}/enrollments', [\App\Http\Controllers\Api\SpreadsheetController::class, 'addEnrollment'])->middleware('permission:create-scores');
    Route::put('/spreadsheet/subject/{subject}/term/{term}/enrollments/{enrollment}', [\App\Http\Controllers\Api\SpreadsheetController::class, 'updateEnrollment'])->middleware('permission:update-scores');

    // ── Grade Boundaries ─────────────────────────────────────────
    Route::get('/grade-boundaries', [\App\Http\Controllers\Api\GradeBoundaryController::class, 'index']);
    Route::put('/grade-boundaries/{gradeBoundary}', [\App\Http\Controllers\Api\GradeBoundaryController::class, 'update'])->middleware('role:admin');

    // ── Assessment Types CRUD ─────────────────────────────────────
    Route::post('/assessment-types', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'code' => 'required|string|max:50|unique:assessment_types,code',
            'name' => 'required|string|max:100',
            'weight_percent' => 'required|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ]);
        $type = \App\Models\AssessmentType::create($request->only('code', 'name', 'weight_percent', 'is_active'));
        return response()->json(['success' => true, 'data' => $type, 'message' => 'Assessment type created.'], 201);
    })->middleware('role:admin');

    Route::put('/assessment-types/{assessmentType}', function (\Illuminate\Http\Request $request, \App\Models\AssessmentType $assessmentType) {
        $request->validate([
            'name' => 'sometimes|string|max:100',
            'weight_percent' => 'sometimes|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean',
        ]);
        $assessmentType->update($request->only('name', 'weight_percent', 'is_active'));
        return response()->json(['success' => true, 'data' => $assessmentType->fresh(), 'message' => 'Assessment type updated.']);
    })->middleware('role:admin');

    Route::delete('/assessment-types/{assessmentType}', function (\App\Models\AssessmentType $assessmentType) {
        if ($assessmentType->scoreDetails()->exists()) {
            return response()->json(['message' => 'Cannot delete: assessment type is in use by score details.'], 409);
        }
        $assessmentType->delete();
        return response()->json(['success' => true, 'message' => 'Assessment type deleted.']);
    })->middleware('role:admin');

    // ── Terms CRUD ────────────────────────────────────────────────
    Route::post('/terms', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'name' => 'required|string|max:100',
            'term_number' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
        $term = \App\Models\Term::create($request->only('name', 'term_number', 'start_date', 'end_date'));
        return response()->json(['success' => true, 'data' => $term, 'message' => 'Term created.'], 201);
    })->middleware('role:admin');

    Route::put('/terms/{term}', function (\Illuminate\Http\Request $request, \App\Models\Term $term) {
        $request->validate([
            'name' => 'sometimes|string|max:100',
            'term_number' => 'sometimes|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);
        $term->update($request->only('name', 'term_number', 'start_date', 'end_date'));
        return response()->json(['success' => true, 'data' => $term->fresh(), 'message' => 'Term updated.']);
    })->middleware('role:admin');

    Route::delete('/terms/{term}', function (\App\Models\Term $term) {
        $term->delete();
        return response()->json(['success' => true, 'message' => 'Term deleted.']);
    })->middleware('role:admin');

    // ── Generations CRUD ──────────────────────────────────────────
    Route::get('/generations', function () {
        return response()->json([
            'success' => true,
            'data' => \App\Models\Generation::orderBy('year', 'desc')->get(),
        ]);
    });

    Route::post('/generations', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'year' => 'required|integer|unique:generations,year',
            'is_current' => 'boolean',
        ]);
        if ($request->is_current) {
            \App\Models\Generation::where('is_current', true)->update(['is_current' => false]);
        }
        $gen = \App\Models\Generation::create($request->only('year', 'is_current'));
        return response()->json(['success' => true, 'data' => $gen, 'message' => 'Generation created.'], 201);
    })->middleware('role:admin');

    Route::put('/generations/{generation}', function (\Illuminate\Http\Request $request, \App\Models\Generation $generation) {
        $request->validate([
            'year' => 'sometimes|integer|unique:generations,year,'.$generation->id,
            'is_current' => 'sometimes|boolean',
        ]);
        if ($request->is_current) {
            \App\Models\Generation::where('is_current', true)->where('id', '!=', $generation->id)->update(['is_current' => false]);
        }
        $generation->update($request->only('year', 'is_current'));
        return response()->json(['success' => true, 'data' => $generation->fresh(), 'message' => 'Generation updated.']);
    })->middleware('role:admin');

    Route::delete('/generations/{generation}', function (\App\Models\Generation $generation) {
        $generation->delete();
        return response()->json(['success' => true, 'message' => 'Generation deleted.']);
    })->middleware('role:admin');

    // ── Report Cards ─────────────────────────────────────────────
    Route::get('/report-cards', [ReportCardController::class, 'index'])->middleware('permission:view-report-cards');
    Route::get('/report-cards/{reportCard}', [ReportCardController::class, 'show'])->middleware('permission:view-report-cards');
    Route::post('/subject-offerings/{offering}/generate-report-cards', [ReportCardController::class, 'generateByOffering'])->middleware('permission:generate-report-cards');

    // ── Transcripts ───────────────────────────────────────────────
    Route::get('/transcripts', [ReportCardController::class, 'transcriptIndex'])->middleware('permission:view-report-cards');
    Route::post('/students/{student}/generate-transcript', [ReportCardController::class, 'generateTranscript'])->middleware('permission:generate-report-cards');

    // ── Google Sheets OAuth Integration ────────────────────────────
    Route::post('/google-sheets/create', [GoogleSheetsController::class, 'createSheet'])->middleware('permission:view-scores');
    Route::post('/google-sheets/import', [GoogleSheetsController::class, 'importSheet'])->middleware('permission:create-scores');
});


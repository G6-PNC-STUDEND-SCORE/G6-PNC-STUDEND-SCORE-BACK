<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChartController;
use App\Http\Controllers\Api\SubjectController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

// Password reset
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Public chart data (unauthenticated)
Route::get('/chart/grade-distribution', [ChartController::class, 'gradeDistribution']);
Route::get('/chart/subject-performance', [ChartController::class, 'subjectPerformance']);
Route::get('/chart/summary', [ChartController::class, 'summary']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::patch('/change-password', [AuthController::class, 'changePassword']);

    // Subject routes
    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::post('/subjects', [SubjectController::class, 'store']);
    Route::get('/subjects/{id}', [SubjectController::class, 'show']);
    Route::put('/subjects/{id}', [SubjectController::class, 'update']);
    Route::delete('/subjects/{id}', [SubjectController::class, 'destroy']);

    // Teacher routes
    Route::get('/teachers', [SubjectController::class, 'teachers']);
});

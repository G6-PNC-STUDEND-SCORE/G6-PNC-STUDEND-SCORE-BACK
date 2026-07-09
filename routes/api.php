<?php

use App\Http\Controllers\Api\AuthController;

use App\Http\Controllers\Api\ChartController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\StudentController;
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

    // Class routes
    Route::get('/classes', [ClassController::class, 'index']);

    // Subject routes
    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::post('/subjects', [SubjectController::class, 'store']);
    Route::get('/subjects/{id}', [SubjectController::class, 'show']);
    Route::put('/subjects/{id}', [SubjectController::class, 'update']);
    Route::delete('/subjects/{id}', [SubjectController::class, 'destroy']);

    // Teacher routes
    Route::get('/teachers', [SubjectController::class, 'teachers']);
<<<<<<< HEAD

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
});
=======
});
>>>>>>> 1c81cb7a3c6a61899ee5f127fd9a9b2197d60751

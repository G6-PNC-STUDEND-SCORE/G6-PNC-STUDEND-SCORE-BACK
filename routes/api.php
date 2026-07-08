<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\SubjectController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Class routes
    Route::get('/classes', [ClassController::class, 'index']);
    Route::post('/classes', [ClassController::class, 'store']);
    Route::get('/classes/{id}', [ClassController::class, 'show']);
    Route::put('/classes/{id}', [ClassController::class, 'update']);
    Route::delete('/classes/{id}', [ClassController::class, 'destroy']);
    Route::post('/classes/import-pdf', [ClassController::class, 'importPDF']);

    // Subject routes
    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::post('/subjects', [SubjectController::class, 'store']);
    Route::get('/subjects/{id}', [SubjectController::class, 'show']);
    Route::put('/subjects/{id}', [SubjectController::class, 'update']);
    Route::delete('/subjects/{id}', [SubjectController::class, 'destroy']);

    // Teacher routes
    Route::get('/teachers', [SubjectController::class, 'teachers']);
});

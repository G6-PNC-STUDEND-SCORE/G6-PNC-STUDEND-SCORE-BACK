<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\StudentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Students
    Route::apiResource('students', StudentController::class);
    Route::put('/students/{student}/assign-class', [StudentController::class, 'assignClass']);

    // Classes (for dropdowns)
    Route::get('/classes/list', [ClassController::class, 'list']);
});

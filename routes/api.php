<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SchedulingController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Scheduling API routes
Route::middleware(['auth'])->prefix('scheduling')->group(function () {
    Route::post('/generate', [SchedulingController::class, 'generate']);
    Route::get('/validate', [SchedulingController::class, 'validateSchedule']);
    Route::get('/statistics', [SchedulingController::class, 'statistics']);
});
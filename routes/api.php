<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SchedulingController;
use App\Http\Controllers\Api\StudentTypeController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Scheduling API routes
Route::middleware(['auth'])->prefix('scheduling')->group(function () {
    Route::post('/generate', [SchedulingController::class, 'generate']);
    Route::get('/validate', [SchedulingController::class, 'validateSchedule']);
    Route::get('/statistics', [SchedulingController::class, 'statistics']);
});

// Student Type Management API routes
Route::middleware(['auth'])->prefix('students')->group(function () {
    // List and filter students
    Route::get('/', [StudentTypeController::class, 'index'])
        ->name('api.students.index');

    // Get student details
    Route::get('{student_id}', [StudentTypeController::class, 'show'])
        ->name('api.students.show');

    // Update student type
    Route::put('{student_id}/type', [StudentTypeController::class, 'updateType'])
        ->name('api.students.update-type');

    // Bulk import/update student types
    Route::post('/bulk/import', [StudentTypeController::class, 'bulkImport'])
        ->name('api.students.bulk-import');

    // Get statistics
    Route::get('/admin/statistics', [StudentTypeController::class, 'statistics'])
        ->name('api.students.statistics');

    // Get available timeslots for type
    Route::get('/timeslots/{student_type}', [StudentTypeController::class, 'getTimeslotsForType'])
        ->name('api.students.timeslots-for-type');
});
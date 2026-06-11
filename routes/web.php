<?php

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SchedulingController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\StudentTypeAdminController;
use App\Http\Controllers\TeacherImportController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect('/dashboard');
    }

    return Inertia::render('Welcome', [
        'auth' => [
            'user' => null,
        ],
        'status' => session('status'),
    ]);
});


// authentication routes (login, register, password reset, etc.)
require __DIR__.'/auth.php';

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::middleware(['role:admin'])->get('/admin/dashboard', [DashboardController::class, 'admin'])
        ->name('admin.dashboard');

    // Admin Student Types Dashboard
    Route::middleware(['role:admin'])->get('/admin/student-types', [StudentTypeAdminController::class, 'index'])
        ->name('admin.student-types');

    // Audit Logs Routes (Admin only)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])
            ->name('audit-logs.index');
        Route::get('/audit-logs/export', [AuditLogController::class, 'export'])
            ->name('audit-logs.export');
    });

    Route::middleware(['role:scheduler'])->get('/scheduler/dashboard', [DashboardController::class, 'scheduler'])
        ->name('scheduler.dashboard');

    Route::middleware(['role:teacher'])->get('/teacher/dashboard', [DashboardController::class, 'teacher'])
        ->name('teacher.dashboard');
    Route::middleware(['role:teacher'])->get('/teacher/schedule', [DashboardController::class, 'teacherSchedule'])
        ->name('teacher.schedule');
    Route::middleware(['role:teacher'])->group(function () {
        Route::get('/teacher/students', [DashboardController::class, 'teacherStudents'])
            ->name('teacher.students');
        Route::get('/export/teacher-students', [ExportController::class, 'exportTeacherStudents'])
            ->name('export.teacher-students');
    });

    Route::middleware(['role:student'])->get('/student/dashboard', [DashboardController::class, 'student'])
        ->name('student.dashboard');
    Route::middleware(['role:student'])->get('/student/schedule', [DashboardController::class, 'studentSchedule'])
        ->name('student.schedule');

    // profile routes used by Breeze/Jetstream-style UI
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('/password', [ChangePasswordController::class, 'update'])
        ->middleware('auth')
        ->name('password.change.update');

    // Change password routes (initial forced password change)
    Route::get('/change-password', [ChangePasswordController::class, 'showForm'])
        ->middleware('auth')
        ->name('change.password');

    Route::post('/change-password', [ChangePasswordController::class, 'update'])
        ->middleware('auth')
        ->name('change.password.update');

    // Unified Entity Management Routes
    Route::prefix('entities')->group(function () {
        Route::get('/{entityType}', [EntityController::class, 'index'])
            ->name('entities.index');

        Route::post('/{entityType}', [EntityController::class, 'store'])
            ->name('entities.store');

        Route::get('/{entityType}/{id}/edit', [EntityController::class, 'edit'])
            ->name('entities.edit');

        Route::put('/{entityType}/{id}', [EntityController::class, 'update'])
            ->name('entities.update');

        Route::delete('/{entityType}/{id}', [EntityController::class, 'destroy'])
            ->name('entities.destroy');

        Route::post('/{entityType}/bulk-delete', [EntityController::class, 'bulkDelete'])
            ->name('entities.bulk-delete');

        Route::get('/{entityType}/export', [EntityController::class, 'export'])
            ->name('entities.export');
    });

    // Convenience routes that redirect to entity management
    Route::redirect('/students', '/entities/students');
    Route::redirect('/teachers', '/entities/teachers');
    Route::redirect('/courses', '/entities/courses');
    Route::redirect('/course-offerings', '/entities/course-offerings');
    Route::redirect('/sections', '/entities/sections');
    Route::redirect('/departments', '/entities/departments');
    Route::redirect('/semesters', '/entities/semesters');
    Route::redirect('/rooms', '/entities/rooms');
    Route::redirect('/timeslots', '/entities/timeslots');
    Route::redirect('/enrollments', '/entities/enrollments');
    Route::redirect('/section-teachers', '/entities/section-teachers');

    // Advanced Scheduling Engine routes
    Route::middleware(['permission:generate schedule'])->prefix('scheduling')->group(function () {
        Route::post('/generate', [SchedulingController::class, 'generate'])
            ->name('scheduling.generate');
        Route::get('/validate', [SchedulingController::class, 'validateSchedule'])
            ->name('scheduling.validate');
        Route::get('/statistics', [SchedulingController::class, 'statistics'])
            ->name('scheduling.statistics');
    });

    // Schedule generation routes (should require permission)
    Route::middleware(['permission:generate schedule'])->group(function () {
        Route::get('/schedules/generate', [ScheduleController::class, 'showGenerateForm'])
            ->name('schedules.generate.show');
        Route::post('/schedules/generate', [ScheduleController::class, 'generate'])
            ->name('schedules.generate');
        Route::post('/schedules/generate-auto', [ScheduleController::class, 'generateAuto'])
            ->name('schedules.generate.auto');
    });

    Route::middleware(['permission:manage schedules'])->group(function () {
        Route::resource('schedules', ScheduleController::class)
            ->except(['index', 'show']);

        Route::post('/schedules/{schedule}/assign-teacher',
            [ScheduleController::class, 'assignTeacher'])
            ->name('schedules.assign-teacher');

        Route::post('/schedules/{schedule}/assign-room',
            [ScheduleController::class, 'assignRoom'])
            ->name('schedules.assign-room');

        Route::post('/schedules/{schedule}/assign-timeslot',
            [ScheduleController::class, 'assignTimeslot'])
            ->name('schedules.assign-timeslot');
    });

    Route::get('/schedules',
        [ScheduleController::class, 'index'])
        ->name('schedules.index');

    Route::get('/settings', function () {
        return Inertia::render('Settings/Index');
    })->name('settings');

    Route::get('/settings/profile', function () {
        return Inertia::render('Settings/Profile');
    })->name('settings.profile');

    Route::get('/settings/system', function () {
        return Inertia::render('Settings/System');
    })->name('settings.system');

    Route::get('/settings/security', function () {
        return Inertia::render('Settings/Security');
    })->name('settings.security');

    Route::get('/schedules/{schedule}',
        [ScheduleController::class, 'show'])
        ->name('schedules.show');

    // Import routes (should require permission)
    Route::middleware(['permission:import data'])->group(function () {
        Route::get('/import', [ImportController::class, 'index'])
            ->name('import.index');

        Route::get('/import/templates/{type}', [ImportController::class, 'downloadTemplate'])
            ->name('import.template');

        // Bulk import route for modern import modal
        Route::post('/import/bulk', [ImportController::class, 'bulkImport'])
            ->name('import.bulk');

        Route::post('/import/students',
            [ImportController::class, 'importStudents'])
            ->name('import.students');

        Route::post('/import/teachers',
            [ImportController::class, 'importTeachers'])
            ->name('import.teachers');

        Route::post('/import/departments',
            [ImportController::class, 'importDepartments'])
            ->name('import.departments');

        Route::post('/import/semesters',
            [ImportController::class, 'importSemesters'])
            ->name('import.semesters');

        Route::post('/import/courses',
            [ImportController::class, 'importCourses'])
            ->name('import.courses');

        Route::post('/import/course-offerings',
            [ImportController::class, 'importCourseOfferings'])
            ->name('import.course-offerings');

        Route::post('/import/sections',
            [ImportController::class, 'importSections'])
            ->name('import.sections');

        Route::post('/import/section-teachers',
            [ImportController::class, 'importSectionTeachers'])
            ->name('import.section-teachers');

        Route::post('/import/timeslots',
            [ImportController::class, 'importTimeslots'])
            ->name('import.timeslots');

        Route::post('/import/preview',
            [ImportController::class, 'previewImport'])
            ->name('import.preview');

        Route::post('/import/rooms',
            [ImportController::class, 'importRooms'])
            ->name('import.rooms');

        Route::post('/import/enrollments',
            [ImportController::class, 'importEnrollments'])
            ->name('import.enrollments');
    });

    Route::middleware(['permission:export schedule'])->group(function () {
        Route::get('/export/schedule',
            [ExportController::class, 'exportSchedule'])
            ->name('export.schedule');

        Route::get('/export/students',
            [ExportController::class, 'exportStudents'])
            ->name('export.students');

        Route::get('/export/teachers',
            [ExportController::class, 'exportTeachers'])
            ->name('export.teachers');

        Route::get('/export/credentials',
            [ExportController::class, 'exportCredentials'])
            ->name('export.credentials');
    });

    Route::middleware(['role:admin'])->group(function () {
        Route::get('/admin/users/create', [DashboardController::class, 'create'])->name('admin.users.create');
        Route::post('/admin/users', [DashboardController::class, 'store'])->name('admin.users.store');

        // Clean import routes (exports credentials immediately)
        Route::post('/import-students', [StudentImportController::class, 'import']);
        Route::post('/import-teachers', [TeacherImportController::class, 'import']);
    });
});

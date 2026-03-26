<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\TimeslotController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SchedulingController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\CourseOfferingController;
use App\Http\Controllers\TeacherImportController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\Auth\ChangePasswordController;

use Inertia\Inertia;

Route::get('/', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }

    $user = Auth::user();
    $role = $user->roles->first()?->name;

    if (! $role) {
        // Try inferring the role from the email prefix for seeded/testing accounts
        $email = strtolower($user->email);
        $roleMap = [
            'admin@' => 'admin',
            'scheduler@' => 'scheduler',
            'teacher@' => 'teacher',
            'student@' => 'student',
        ];

        foreach ($roleMap as $prefix => $mappedRole) {
            if (str_starts_with($email, $prefix)) {
                $user->assignRole($mappedRole);
                $role = $mappedRole;
                break;
            }
        }
    }

    if (! $role) {
        Auth::logout();
        return redirect()->route('login')
            ->with('status', 'Your account is not assigned a role. Please contact an administrator.');
    }

    switch ($role) {
        case 'admin':
            return app(DashboardController::class)->admin();
        case 'scheduler':
            return app(DashboardController::class)->scheduler();
        case 'teacher':
            return app(DashboardController::class)->teacher();
        case 'student':
            return app(DashboardController::class)->student();
        default:
            return redirect()->route('login');
    }
});

// authentication routes (login, register, password reset, etc.)
require __DIR__.'/auth.php';

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::middleware(['role:admin'])->get('/admin/dashboard', [DashboardController::class, 'admin'])
        ->name('admin.dashboard');

    Route::middleware(['role:scheduler'])->get('/scheduler/dashboard', [DashboardController::class, 'scheduler'])
        ->name('scheduler.dashboard');

    Route::middleware(['role:teacher'])->get('/teacher/dashboard', [DashboardController::class, 'teacher'])
        ->name('teacher.dashboard');
    Route::middleware(['role:teacher'])->get('/teacher/schedule', [DashboardController::class, 'teacherSchedule'])
        ->name('teacher.schedule');

    Route::middleware(['role:student'])->get('/student/dashboard', [DashboardController::class, 'student'])
        ->name('student.dashboard');
    Route::middleware(['role:student'])->get('/student/schedule', [DashboardController::class, 'studentSchedule'])
        ->name('student.schedule');

    // profile routes used by Breeze/Jetstream-style UI
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

    // Change password routes
    Route::get('/change-password', [ChangePasswordController::class, 'showForm'])
        ->middleware('auth')
        ->name('password.change');
    Route::post('/change-password', [ChangePasswordController::class, 'update'])
        ->middleware('auth')
        ->name('password.change.update');

    Route::middleware(['permission:manage students'])
        ->resource('students', StudentController::class);

    Route::middleware(['permission:manage teachers'])
        ->resource('teachers', TeacherController::class);

    Route::middleware(['permission:manage courses'])
        ->resource('courses', CourseController::class);

    Route::middleware(['permission:manage courses'])
        ->resource('course-offerings', CourseOfferingController::class);

    Route::middleware(['permission:manage courses'])
        ->resource('sections', SectionController::class);

    Route::middleware(['permission:manage departments'])
        ->resource('departments', DepartmentController::class);

    Route::middleware(['permission:manage semesters'])
        ->resource('semesters', SemesterController::class);

    Route::middleware(['permission:manage rooms'])
        ->resource('rooms', RoomController::class);

    Route::middleware(['permission:manage timeslots'])
        ->resource('timeslots', TimeslotController::class);

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
            ->except(['index','show']);

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
            [ScheduleController::class,'index'])
            ->name('schedules.index');

    Route::get('/schedules/{schedule}',
            [ScheduleController::class,'show'])
            ->name('schedules.show');

    // Import routes (should require permission)
    Route::middleware(['permission:import data'])->group(function () {
        Route::get('/import', [ImportController::class, 'index'])
            ->name('import.index');

        Route::post('/import/students',
            [ImportController::class,'importStudents'])
            ->name('import.students');

        Route::post('/import/teachers',
            [ImportController::class,'importTeachers'])
            ->name('import.teachers');

        Route::post('/import/courses',
            [ImportController::class,'importCourses'])
            ->name('import.courses');

        Route::post('/import/course-offerings',
            [ImportController::class,'importCourseOfferings'])
            ->name('import.course-offerings');

        Route::post('/import/sections',
            [ImportController::class,'importSections'])
            ->name('import.sections');

        Route::post('/import/section-teachers',
            [ImportController::class,'importSectionTeachers'])
            ->name('import.section-teachers');

        Route::post('/import/timeslots',
            [ImportController::class,'importTimeslots'])
            ->name('import.timeslots');

        Route::post('/import/rooms',
            [ImportController::class,'importRooms'])
            ->name('import.rooms');

        Route::post('/import/enrollments',
            [ImportController::class,'importEnrollments'])
            ->name('import.enrollments');
    });

    Route::middleware(['permission:export schedule'])->group(function () {
        Route::get('/export/schedule',
            [ExportController::class,'exportSchedule'])
            ->name('export.schedule');

        Route::get('/export/students',
            [ExportController::class,'exportStudents'])
            ->name('export.students');

        Route::get('/export/teachers',
            [ExportController::class,'exportTeachers'])
            ->name('export.teachers');

        Route::get('/export/credentials',
            [ExportController::class,'exportCredentials'])
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

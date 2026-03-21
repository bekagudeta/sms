<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Course;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    // Student schedule view (timetable)
    public function studentSchedule()
    {
        $user = Auth::user();
        $student = $this->resolveStudent($user);

        if (!$student) {
            // If no student profile is found, show generic schedule entries
            $schedules = Schedule::with(['course', 'teacher.user', 'room', 'timeslot'])
                ->latest()
                ->take(20)
                ->get();
            return view('student.schedule', compact('schedules'));
        }

        $schedules = $this->buildStudentScheduleQuery($student)->get();

        // fallback to broader schedule when no exact matches are found
        if ($schedules->isEmpty()) {
            $schedules = Schedule::with(['course', 'teacher.user', 'room', 'timeslot'])
                ->latest()
                ->take(20)
                ->get();
        }

        return view('student.schedule', compact('schedules'));
    }

    // Teacher schedule view (timetable)
    public function teacherSchedule()
    {
        $user = Auth::user();
        $teacher = $user->teacher;
        if (!$teacher) {
            abort(403, 'Teacher profile missing');
        }
        $schedules = Schedule::with(['course', 'room', 'timeslot'])
            ->where('teacher_id', $teacher->id)
            ->get();
        return view('teacher.schedule', compact('schedules'));
    }
    public function index()
    {
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
                    // Ensure the role exists before assigning
                    Role::findOrCreate($mappedRole, 'web');

                    $user->assignRole($mappedRole);
                    $role = $mappedRole;

                    // Ensure student/teacher record exists with basic contact details (for team integration).
                    if ($role === 'student' && !$user->student) {
                        Student::firstOrCreate(
                            ['user_id' => $user->id],
                            [
                                'email' => $user->email,
                                'first_name' => explode(' ', trim($user->name))[0] ?? null,
                                'last_name' => trim(str_replace(explode(' ', trim($user->name))[0] ?? '', '', $user->name)),
                                'semester' => 1,
                                'section' => 'A',
                            ]
                        );
                    }
                    if ($role === 'teacher' && !$user->teacher) {
                        Teacher::firstOrCreate(
                            ['user_id' => $user->id],
                            [
                                'email' => $user->email,
                                'first_name' => explode(' ', trim($user->name))[0] ?? null,
                                'last_name' => trim(str_replace(explode(' ', trim($user->name))[0] ?? '', '', $user->name)),
                            ]
                        );
                    }
                    break;
                }
            }
        }

        if (! $role) {
            // If a user somehow made it here without a role, sign them out and
            // send them back to login with a clear message.
            auth()->guard()->logout();
            return redirect()->route('login')
                ->with('status', 'Your account is not assigned a role. Please contact an administrator.');
        }

        switch ($role) {
            case 'admin':
                return $this->admin();
            case 'scheduler':
                return $this->scheduler();
            case 'teacher':
                return $this->teacher();
            case 'student':
                return $this->student();
            default:
                // should never happen once role exists, but keep safe fallback
                return redirect()->route('login');
        }
    }

    public function admin()
    {
        $user = Auth::user();
        $role = $user->roles->first()?->name;

        $pendingCredentials = 0;
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'must_change_password') && Schema::hasColumn('users', 'plain_password')) {
            $pendingCredentials = User::where('must_change_password', true)
                ->whereNotNull('plain_password')
                ->count();
        }

        $stats = [
            'total_students' => Student::count(),
            'total_teachers' => Teacher::count(),
            'total_courses' => Course::count(),
            'total_schedules' => Schedule::count(),
            'pending_credentials' => $pendingCredentials,
        ];

        $recentSchedules = Schedule::with(['course', 'teacher', 'room', 'timeslot'])
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'recentSchedules' => $recentSchedules,
            'role' => $role
        ]);
    }

    public function scheduler()
    {
        $user = Auth::user();
        $role = $user->roles->first()?->name;

        $stats = ['total_schedules' => Schedule::count()];
        $recentSchedules = Schedule::with(['course', 'teacher', 'room', 'timeslot'])
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'recentSchedules' => $recentSchedules,
            'role' => $role,
        ]);
    }

    public function teacher()
    {
        $user = Auth::user();
        $role = $user->roles->first()?->name;

        $teacher = $user->teacher;
        $recentSchedules = collect();
        if ($teacher) {
            $recentSchedules = Schedule::where('teacher_id', $teacher->id)
                ->with(['course', 'teacher', 'room', 'timeslot'])
                ->latest()
                ->take(5)
                ->get();
        }

        $stats = []; // teacher dashboard does not display global stats
        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'recentSchedules' => $recentSchedules,
            'role' => $role,
        ]);
    }

    public function student()
    {
        $user = Auth::user();
        $role = $user->roles->first()?->name;

        $stats = [];

        // Find the student record so we can filter schedules for their semester.
        $student = $this->resolveStudent($user);

        $recentSchedules = collect();
        if ($student) {
            $recentSchedules = $this->buildStudentScheduleQuery($student)
                ->latest()
                ->take(5)
                ->get();

            if ($recentSchedules->isEmpty()) {
                $recentSchedules = Schedule::with(['course', 'teacher', 'room', 'timeslot'])
                    ->latest()
                    ->take(5)
                    ->get();
            }
        }

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'recentSchedules' => $recentSchedules,
            'role' => $role,
        ]);
    }

    private function resolveStudent($user)
    {
        if ($user->student) {
            return $user->student;
        }

        $student = Student::where('user_id', $user->id)
            ->orWhere('email', $user->email)
            ->first();

        if ($student) {
            return $student;
        }

        if ($user->hasRole('student')) {
            return Student::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'first_name' => explode(' ', trim($user->name))[0] ?? null,
                'last_name' => trim(str_replace(explode(' ', trim($user->name))[0] ?? '', '', $user->name)),
                'semester' => 1,
                'section' => 'A'
            ]);
        }

        return null;
    }

    private function buildStudentScheduleQuery(Student $student)
    {
        $query = Schedule::with(['course', 'teacher.user', 'room', 'timeslot']);

        if ($student->department_id) {
            $query->whereHas('course', function ($q) use ($student) {
                $q->where('department_id', $student->department_id);

                if (!empty($student->level)) {
                    $q->where('level', $student->level);
                }
            });
        } elseif (!empty($student->level)) {
            $query->whereHas('course', function ($q) use ($student) {
                $q->where('level', $student->level);
            });
        }

        if (!empty($student->semester)) {
            $semesterModel = Semester::where('name', $student->semester . ' Semester')->first();
            if ($semesterModel) {
                $query->where('semester_id', $semesterModel->id);
            } elseif (is_numeric($student->semester)) {
                $query->where('semester_id', (int) $student->semester);
            }
        }

        if (!empty($student->section)) {
            $query->where('section', $student->section);
        }

        return $query;
    }

    public function create()
    {
        $roles = Role::all();
        $departments = Department::all();
        return Inertia::render('Admin/Users/Create', [
            'roles' => $roles,
            'departments' => $departments
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|exists:roles,name',
            'department_id' => 'required_if:role,student,teacher|nullable|exists:departments,id',
            'level' => 'required_if:role,student|nullable|string',
            'section' => 'required_if:role,student|nullable|string',
            'max_hours_per_week' => 'required_if:role,teacher|nullable|integer|min:1|max:40',
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'must_change_password' => false,
                'plain_password' => null,
            ]);
            $user->assignRole($validated['role']);

            if ($validated['role'] === 'student') {
                $nameParts = explode(' ', $validated['name'], 2);
                $firstName = $nameParts[0] ?? '';
                $lastName = $nameParts[1] ?? '';
                
                Student::create([
                    'user_id' => $user->id,
                    'student_id' => 'STU' . str_pad($user->id, 6, '0', STR_PAD_LEFT),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $validated['email'],
                    'department_id' => $validated['department_id'],
                    'semester' => 1, // Default to first semester
                    'level' => $validated['level'],
                    'section' => $validated['section'],
                    'enrollment_date' => now()->toDateString(),
                ]);
            }
            if ($validated['role'] === 'teacher') {
                $nameParts = explode(' ', $validated['name'], 2);
                $firstName = $nameParts[0] ?? '';
                $lastName = $nameParts[1] ?? '';
                
                Teacher::create([
                    'user_id' => $user->id,
                    'teacher_id' => 'TCH' . str_pad($user->id, 6, '0', STR_PAD_LEFT),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $validated['email'],
                    'department_id' => $validated['department_id'],
                    'max_hours_per_week' => $validated['max_hours_per_week'],
                ]);
            }
        });

        return redirect()->route('admin.dashboard')->with('success', 'User created successfully.');
    }
}
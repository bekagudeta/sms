<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Course;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Department;
use App\Repositories\ScheduleRepository;
use App\Services\TeacherStudentService;
use App\Support\TeacherScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        protected ScheduleRepository $scheduleRepository,
        protected TeacherStudentService $teacherStudentService
    ) {}

    protected function scheduleEagerLoads(): array
    {
        return [
            'section.courseOffering.course.department',
            'section.courseOffering.semester',
            'section.teachers.user',
            'section.enrollments.student',
            'room',
            'timeslot',
        ];
    }

    // Student schedule view (timetable)
    public function studentSchedule()
    {
        $user = Auth::user();
        $student = $this->resolveStudent($user);

        if (!$student) {
            // If no student profile is found, show generic schedule entries
            $schedules = Schedule::with($this->scheduleEagerLoads())
                ->latest()
                ->take(20)
                ->get();
            return view('student.schedule', compact('schedules'));
        }

        try {
            $schedules = $this->buildStudentScheduleQuery($student)->get();

            // fallback to broader schedule when no exact matches are found
            if ($schedules->isEmpty()) {
                $schedules = Schedule::with($this->scheduleEagerLoads())
                    ->latest()
                    ->take(20)
                    ->get();
            }
        } catch (\Exception $e) {
            \Log::error('Error in studentSchedule method: ' . $e->getMessage(), [
                'student_id' => $student->id,
                'user_id' => $user->id
            ]);

            $schedules = Schedule::with($this->scheduleEagerLoads())
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
        $schedules = Schedule::with($this->scheduleEagerLoads())
            ->whereHas('section.teachers', function ($query) use ($teacher) {
                TeacherScope::wherePrimaryKey($query, $teacher->id);
            })
            ->join('timeslots', 'schedules.timeslot_id', '=', 'timeslots.id')
            ->orderBy('timeslots.day_of_week')
            ->orderBy('timeslots.start_time')
            ->select('schedules.*')
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
                    Role::firstOrCreate(['name' => $mappedRole, 'guard_name' => 'web']);

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
                                'academic_section' => 'Unassigned',
                                'department_id' => Department::query()->value('id'),
                                'enrollment_date' => now(),
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

        $recentSchedules = Schedule::with(['section.courseOffering.course', 'section.teachers.user', 'room', 'timeslot'])
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

        $scheduledSectionIds = Schedule::query()->distinct()->pluck('section_id');
        $activeSemester = Semester::where('is_active', true)->first();

        $stats = [
            'total_schedules' => Schedule::count(),
            'total_sections' => Section::count(),
            'unscheduled_sections' => Section::whereNotIn('id', $scheduledSectionIds)->count(),
        ];

        $recentSchedules = Schedule::with($this->scheduleEagerLoads())
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'recentSchedules' => $recentSchedules,
            'activeSemester' => $activeSemester ? [
                'id' => $activeSemester->id,
                'name' => $activeSemester->name,
                'academic_year' => $activeSemester->resolved_academic_year,
            ] : null,
            'role' => $role,
        ]);
    }

    public function teacherStudents(Request $request)
    {
        $teacher = Auth::user()?->teacher;
        abort_unless($teacher, 403, 'Teacher profile missing.');

        $semesters = Semester::orderByDesc('start_date')->get();
        $semesterId = $request->integer('semester_id')
            ?: Semester::where('is_active', true)->value('id');

        $students = $this->teacherStudentService->studentsForTeacher(
            $teacher,
            $semesterId ?: null
        );

        $currentSemester = $semesters->firstWhere('id', $semesterId);

        return Inertia::render('Teacher/Students', [
            'students' => $students,
            'semesters' => $semesters->map(fn ($semester) => [
                'id' => $semester->id,
                'name' => $semester->name,
                'academic_year' => $semester->resolved_academic_year,
                'is_active' => $semester->is_active,
            ])->values(),
            'currentSemesterId' => $semesterId,
            'currentSemester' => $currentSemester ? [
                'id' => $currentSemester->id,
                'name' => $currentSemester->name,
                'academic_year' => $currentSemester->resolved_academic_year,
            ] : null,
        ]);
    }

    public function teacher()
    {
        $user = Auth::user();
        $role = $user->roles->first()?->name;

        $teacher = $user->teacher;
        $recentSchedules = collect();
        $myStudents = collect();
        $activeSemester = Semester::where('is_active', true)->first();

        if ($teacher) {
            $recentSchedules = $this->scheduleRepository
                ->getByTeacher($teacher->id)
                ->sortBy([
                    fn ($s) => array_search($s->timeslot?->day_of_week, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], true) ?: 99,
                    fn ($s) => $s->timeslot?->start_time,
                ])
                ->values();

            $myStudents = $this->teacherStudentService->studentsForTeacher(
                $teacher,
                $activeSemester?->id
            );
        }

        return Inertia::render('Dashboard/Index', [
            'stats' => [],
            'recentSchedules' => $recentSchedules,
            'myStudents' => $myStudents,
            'activeSemester' => $activeSemester ? [
                'id' => $activeSemester->id,
                'name' => $activeSemester->name,
                'academic_year' => $activeSemester->resolved_academic_year,
            ] : null,
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
            try {
                $recentSchedules = $this->buildStudentScheduleQuery($student)
                    ->latest()
                    ->take(5)
                    ->get();

            } catch (\Exception $e) {
                // Log the error for debugging but don't expose it to the user
                \Log::error('Error building student schedule query: ' . $e->getMessage(), [
                    'student_id' => $student->id,
                    'user_id' => $user->id
                ]);
                
                // Fallback to basic schedule query
                $recentSchedules = Schedule::with(['section.courseOffering.course', 'section.teachers.user', 'room', 'timeslot'])
                    ->latest()
                    ->take(5)
                    ->get();
            }
        }

        $studentProfile = null;
        $enrolledCourses = [];

        if ($student) {
            $student->load(['department', 'enrollments.section.courseOffering.course', 'enrollments.section.courseOffering.semester']);

            $studentProfile = [
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'academic_section' => $student->academic_section,
                'department' => $student->department?->name,
            ];

            // Get enrolled courses from explicit enrollments
            $enrolledCourses = $student->enrollments->map(function ($enrollment) {
                $section = $enrollment->section;
                $course = $section?->courseOffering?->course;

                return [
                    'course_code' => $course?->course_code,
                    'course_name' => $course?->course_name,
                    'section_name' => $section?->section_name,
                    'semester' => $section?->courseOffering?->semester?->name,
                ];
            })->filter(fn ($row) => $row['course_code'])->values()->all();

            // Fallback: If no explicit enrollments, show department courses from active semester
            if (empty($enrolledCourses) && $student->department_id) {
                $activeSemester = Semester::where('is_active', true)->first();
                if ($activeSemester) {
                    $departmentSections = Section::whereHas('courseOffering', function ($q) use ($student, $activeSemester) {
                        $q->where('semester_id', $activeSemester->id)
                            ->whereHas('course', function ($courseQuery) use ($student) {
                                $courseQuery->where('department_id', $student->department_id);
                            });
                    })->with('courseOffering.course', 'courseOffering.semester')
                        ->get();

                    $enrolledCourses = $departmentSections->map(function ($section) {
                        $course = $section?->courseOffering?->course;
                        return [
                            'course_code' => $course?->course_code,
                            'course_name' => $course?->course_name,
                            'section_name' => $section?->section_name,
                            'semester' => $section?->courseOffering?->semester?->name,
                        ];
                    })->filter(fn ($row) => $row['course_code'])->values()->all();
                }
            }
        }

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'recentSchedules' => $recentSchedules,
            'role' => $role,
            'studentProfile' => $studentProfile,
            'enrolledCourses' => $enrolledCourses,
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
                'academic_section' => 'Unassigned',
                'department_id' => Department::query()->value('id'),
                'enrollment_date' => now(),
            ]);
        }

        return null;
    }

    private function buildStudentScheduleQuery(Student $student)
    {
        $query = Schedule::with($this->scheduleEagerLoads());

        $activeSemester = Semester::where('is_active', true)->first();
        if ($activeSemester) {
            $query->whereHas('section.courseOffering', function ($q) use ($activeSemester) {
                $q->where('semester_id', $activeSemester->id);
            });
        }

        $enrolledSectionIds = $student->enrollments()->pluck('section_id');

        if ($enrolledSectionIds->isNotEmpty()) {
            // Student has explicit enrollments - show only enrolled sections
            $query->whereIn('section_id', $enrolledSectionIds);
        } else {
            // Fallback: Show all sections for the student's department
            // This ensures students see their academic cohort's schedules even without explicit enrollments
            if ($student->department_id) {
                $query->whereHas('section.courseOffering.course', function ($q) use ($student) {
                    $q->where('department_id', $student->department_id);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
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
            'academic_section' => 'required_if:role,student|nullable|string|max:50',
            'max_hours_per_week' => 'required_if:role,teacher|nullable|integer|min:1|max:38',
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'must_change_password' => false,
                'plain_password' => $validated['password'],
                'role' => $validated['role'],
            ]);
            $user->syncRoles([$validated['role']]);

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
                    'level' => $validated['level'],
                    'academic_section' => $validated['academic_section'],
                    'enrollment_date' => now()->toDateString(),
                ]);
            } elseif ($validated['role'] === 'teacher') {
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

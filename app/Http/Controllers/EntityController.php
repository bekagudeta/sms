<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EntityController extends Controller
{
    protected $entityMap = [
        'students' => [
            'model' => 'App\Models\Student',
            'controller' => 'App\Http\Controllers\StudentController',
            'permissions' => [
                'view' => 'view students',
                'create' => 'create students',
                'edit' => 'edit students',
                'delete' => 'delete students',
                'import' => 'import students',
            ],
        ],
        'teachers' => [
            'model' => 'App\Models\Teacher',
            'controller' => 'App\Http\Controllers\TeacherController',
            'permissions' => [
                'view' => 'view teachers',
                'create' => 'create teachers',
                'edit' => 'edit teachers',
                'delete' => 'delete teachers',
                'import' => 'import teachers',
            ],
        ],
        'courses' => [
            'model' => 'App\Models\Course',
            'controller' => 'App\Http\Controllers\CourseController',
            'permissions' => [
                'view' => 'view courses',
                'create' => 'create courses',
                'edit' => 'edit courses',
                'delete' => 'delete courses',
                'import' => 'import courses',
            ],
        ],
        'course-offerings' => [
            'model' => 'App\Models\CourseOffering',
            'controller' => 'App\Http\Controllers\CourseOfferingController',
            'permissions' => [
                'view' => 'view courses',
                'create' => 'create courses',
                'edit' => 'edit courses',
                'delete' => 'delete courses',
                'import' => 'import course-offerings',
            ],
        ],
        'departments' => [
            'model' => 'App\Models\Department',
            'controller' => null,
            'permissions' => [
                'view' => 'view departments',
                'create' => 'create departments',
                'edit' => 'edit departments',
                'delete' => 'delete departments',
                'import' => 'import departments',
            ],
        ],
        'semesters' => [
            'model' => 'App\Models\Semester',
            'controller' => null,
            'permissions' => [
                'view' => 'view semesters',
                'create' => 'create semesters',
                'edit' => 'edit semesters',
                'delete' => 'delete semesters',
                'import' => 'import semesters',
            ],
        ],
        'sections' => [
            'model' => 'App\Models\Section',
            'controller' => 'App\Http\Controllers\SectionController',
            'permissions' => [
                'view' => 'view courses',
                'create' => 'create courses',
                'edit' => 'edit courses',
                'delete' => 'delete courses',
                'import' => 'import sections',
            ],
        ],
        'rooms' => [
            'model' => 'App\Models\Room',
            'controller' => 'App\Http\Controllers\RoomController',
            'permissions' => [
                'view' => 'view rooms',
                'create' => 'create rooms',
                'edit' => 'edit rooms',
                'delete' => 'delete rooms',
                'import' => 'import rooms',
            ],
        ],
        'timeslots' => [
            'model' => 'App\Models\Timeslot',
            'controller' => 'App\Http\Controllers\TimeslotController',
            'permissions' => [
                'view' => 'view timeslots',
                'create' => 'create timeslots',
                'edit' => 'edit timeslots',
                'delete' => 'delete timeslots',
                'import' => 'import timeslots',
            ],
        ],
        'enrollments' => [
            'model' => 'App\Models\Enrollment',
            'controller' => null, // Handle directly in this controller
            'permissions' => [
                'view' => 'view enrollments',
                'create' => 'create enrollments',
                'edit' => 'edit enrollments',
                'delete' => 'delete enrollments',
                'import' => 'import enrollments',
            ],
        ],
        'section-teachers' => [
            'model' => 'App\Models\SectionTeacher',
            'controller' => null, // Handle directly in this controller
            'permissions' => [
                'view' => 'view section-teachers',
                'create' => 'create section-teachers',
                'edit' => 'edit section-teachers',
                'delete' => 'delete section-teachers',
                'import' => 'import section-teachers',
            ],
        ],
    ];

    public function index(Request $request, $entityType)
    {
        if (! isset($this->entityMap[$entityType])) {
            abort(404, 'Entity type not found');
        }

        $entityConfig = $this->entityMap[$entityType];
        $permissions = $entityConfig['permissions'];

        // Check if user is admin - admins have all permissions
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');

        // Check view permission (admin bypass)
        if (! $isAdmin && ! auth()->user()->can($permissions['view'])) {
            abort(403, 'Unauthorized');
        }

        $model = $entityConfig['model'];

        $data = collect([]);

        if (class_exists($model)) {
            try {
                $query = $model::query();

                // Load relationships for nested columns in generic list views
                $relationIncludes = [];
                switch ($entityType) {
                    case 'students':
                    case 'teachers':
                        $relationIncludes = ['department'];
                        break;
                    case 'enrollments':
                        $relationIncludes = [
                            'student',
                            'section.courseOffering.course',
                            'section.courseOffering.semester',
                        ];
                        break;
                    case 'section-teachers':
                        $relationIncludes = [
                            'section.courseOffering.course',
                            'section.courseOffering.semester',
                            'teacher',
                        ];
                        break;
                    case 'course-offerings':
                        $relationIncludes = ['course', 'semester'];
                        break;
                    case 'sections':
                        $relationIncludes = ['courseOffering.course', 'courseOffering.semester'];
                        break;
                    case 'courses':
                        $relationIncludes = ['department'];
                        break;
                }

                if (! empty($relationIncludes)) {
                    $query = $query->with($relationIncludes);
                }

                $data = $query->paginate(15)->withQueryString();
            } catch (\Exception $e) {
                report($e);
                abort(500, 'Unable to load entity data.');
            }
        }

        $relatedOptions = [];

        if ($entityType === 'students') {
            $relatedOptions = [
                'departments' => Department::select('id', 'name')->orderBy('name')->get(),
                'studentTypes' => [
                    ['value' => 'regular', 'label' => 'Regular'],
                    ['value' => 'weekend', 'label' => 'Weekend'],
                ],
                'levels' => [
                    ['value' => 'bachelor', 'label' => 'Bachelor'],
                    ['value' => 'master', 'label' => 'Master'],
                    ['value' => 'phd', 'label' => 'PhD'],
                    ['value' => 'diploma', 'label' => 'Diploma'],
                    ['value' => 'certificate', 'label' => 'Certificate'],
                ],
            ];
        }

        if ($entityType === 'teachers') {
            $relatedOptions = [
                'departments' => Department::select('id', 'name')->orderBy('name')->get(),
                'qualifications' => [
                    ['value' => 'BSc', 'label' => 'BSc'],
                    ['value' => 'MSc', 'label' => 'MSc'],
                    ['value' => 'PhD', 'label' => 'PhD'],
                    ['value' => 'MBA', 'label' => 'MBA'],
                    ['value' => 'MA', 'label' => 'MA'],
                    ['value' => 'EdD', 'label' => 'EdD'],
                    ['value' => 'Other', 'label' => 'Other'],
                ],
            ];
        }

        if ($entityType === 'courses') {
            $relatedOptions = [
                'departments' => Department::select('id', 'name')->orderBy('name')->get(),
                'levels' => [
                    ['value' => 'undergraduate', 'label' => 'Undergraduate'],
                    ['value' => 'graduate', 'label' => 'Graduate'],
                    ['value' => 'certificate', 'label' => 'Certificate'],
                    ['value' => 'professional', 'label' => 'Professional'],
                ],
                'roomTypes' => [
                    ['value' => 'lecture', 'label' => 'Lecture'],
                    ['value' => 'lab', 'label' => 'Laboratory'],
                    ['value' => 'seminar', 'label' => 'Seminar'],
                    ['value' => 'conference', 'label' => 'Conference'],
                    ['value' => 'studio', 'label' => 'Studio'],
                ],
            ];
        }

        if ($entityType === 'course-offerings') {
            $relatedOptions = [
                'courses' => Course::select('id', 'course_code')->orderBy('course_code')->get(),
                'semesters' => Semester::select('id', 'name', 'code', 'academic_year')
                    ->orderBy('name')
                    ->get()
                    ->map(fn ($semester) => [
                        'id' => $semester->id,
                        'name' => $semester->name,
                        'label' => trim(sprintf(
                            '%s (%s)',
                            $semester->name,
                            $semester->academic_year ?: $semester->code
                        )),
                    ]),
            ];
        }

        if ($entityType === 'sections') {
            $relatedOptions = [
                'courseOfferings' => CourseOffering::with(['course', 'semester'])
                    ->get()
                    ->map(function ($offering) {
                        return [
                            'id' => $offering->id,
                            'label' => trim(sprintf(
                                '%s / %s - %s',
                                $offering->course?->course_code,
                                $offering->course?->course_name,
                                $offering->semester?->name
                            )),
                        ];
                    }),
            ];
        }

        if ($entityType === 'enrollments') {
            $relatedOptions = [
                'students' => Student::select('id', 'student_id', 'first_name', 'last_name')
                    ->orderBy('student_id')
                    ->get()
                    ->map(function ($student) {
                        return [
                            'id' => $student->id,
                            'student_id' => $student->student_id,
                            'label' => trim(sprintf(
                                '%s - %s %s',
                                $student->student_id,
                                $student->first_name,
                                $student->last_name
                            )),
                        ];
                    }),
                'sections' => Section::with(['courseOffering.course', 'courseOffering.semester'])
                    ->get()
                    ->map(function ($section) {
                        return [
                            'id' => $section->id,
                            'label' => trim(sprintf(
                                '%s [%s - %s]',
                                $section->section_name,
                                $section->courseOffering?->course?->course_code,
                                $section->courseOffering?->semester?->name
                            )),
                        ];
                    }),
            ];
        }

        if ($entityType === 'section-teachers') {
            $relatedOptions = [
                'sections' => Section::with(['courseOffering.course', 'courseOffering.semester'])
                    ->get()
                    ->map(function ($section) {
                        return [
                            'id' => $section->id,
                            'label' => trim(sprintf(
                                '%s [%s - %s]',
                                $section->section_name,
                                $section->courseOffering?->course?->course_code,
                                $section->courseOffering?->semester?->name
                            )),
                        ];
                    }),
                'teachers' => Teacher::select('id', 'first_name', 'last_name')
                    ->orderBy('last_name')
                    ->get()
                    ->map(function ($teacher) {
                        return [
                            'id' => $teacher->id,
                            'label' => trim(sprintf(
                                '%s %s',
                                $teacher->first_name,
                                $teacher->last_name
                            )),
                        ];
                    }),
            ];
        }

        // Always render the Entities/Index view with the entity data
        return Inertia::render('Entities/Index', [
            'entityType' => $entityType,
            'data' => $data,
            'filters' => $request->only(['search', 'status', 'department']),
            'permissions' => [
                'view' => $isAdmin || auth()->user()->can($permissions['view']),
                'create' => $isAdmin || auth()->user()->can($permissions['create']),
                'edit' => $isAdmin || auth()->user()->can($permissions['edit']),
                'delete' => $isAdmin || auth()->user()->can($permissions['delete']),
                'import' => $isAdmin || auth()->user()->can($permissions['import']),
            ],
            'relatedOptions' => $relatedOptions,
        ]);
    }

    public function store(Request $request, $entityType)
    {
        if (! isset($this->entityMap[$entityType])) {
            abort(404, 'Entity type not found');
        }

        $entityConfig = $this->entityMap[$entityType];

        // Check if user is admin - admins have all permissions
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');

        // Check create permission (admin bypass)
        if (! $isAdmin && ! auth()->user()->can($entityConfig['permissions']['create'])) {
            abort(403, 'Unauthorized');
        }

        return $this->handleEntityStore($request, $entityType);
    }

    public function update(Request $request, $entityType, $id)
    {
        if (! isset($this->entityMap[$entityType])) {
            abort(404, 'Entity type not found');
        }

        $entityConfig = $this->entityMap[$entityType];

        // Check if user is admin - admins have all permissions
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');

        // Check edit permission (admin bypass)
        if (! $isAdmin && ! auth()->user()->can($entityConfig['permissions']['edit'])) {
            abort(403, 'Unauthorized');
        }

        return $this->handleEntityUpdate($request, $entityType, $id);
    }

    public function destroy(Request $request, $entityType, $id)
    {
        if (! isset($this->entityMap[$entityType])) {
            abort(404, 'Entity type not found');
        }

        $entityConfig = $this->entityMap[$entityType];

        // Check if user is admin - admins have all permissions
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');

        // Check delete permission (admin bypass)
        if (! $isAdmin && ! auth()->user()->can($entityConfig['permissions']['delete'])) {
            abort(403, 'Unauthorized');
        }

        return $this->handleEntityDestroy($entityType, $id);
    }

    public function bulkDelete(Request $request, $entityType)
    {
        if (! isset($this->entityMap[$entityType])) {
            abort(404, 'Entity type not found');
        }

        $entityConfig = $this->entityMap[$entityType];

        // Check if user is admin - admins have all permissions
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');

        // Check delete permission (admin bypass)
        if (! $isAdmin && ! auth()->user()->can($entityConfig['permissions']['delete'])) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        $model = $entityConfig['model'];
        $deleted = $model::whereIn('id', $request->ids)->delete();

        return back()->with('success', "Successfully deleted {$deleted} items.");
    }

    public function export(Request $request, $entityType)
    {
        if (! isset($this->entityMap[$entityType])) {
            abort(404, 'Entity type not found');
        }

        $entityConfig = $this->entityMap[$entityType];

        // Check if user is admin - admins have all permissions
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');

        // Check view permission (admin bypass)
        if (! $isAdmin && ! auth()->user()->can($entityConfig['permissions']['view'])) {
            abort(403, 'Unauthorized');
        }

        // Implementation for export functionality
        // This would generate CSV/Excel exports
        return response()->json(['message' => 'Export functionality coming soon']);
    }

    private function handleEntityStore(Request $request, $entityType)
    {
        $model = $this->entityMap[$entityType]['model'];

        // Define validation rules for entities without dedicated controllers
        $validationRules = $this->getValidationRules($entityType);
        $validated = $request->validate($validationRules);

        if ($entityType === 'students') {
            $validated['user_id'] = auth()->id();

            if (empty($validated['enrollment_date'])) {
                $validated['enrollment_date'] = now()->toDateString();
            }
        }

        if ($entityType === 'enrollments' && isset($validated['student_code_value'])) {
            $validated['student_code'] = $validated['student_code_value'];
            unset($validated['student_code_value']);
        }

        $entity = $model::create($validated);

        return redirect()->route('entities.index', ['entityType' => $entityType])
            ->with('success', ucfirst($entityType).' created successfully.');
    }

    private function handleEntityUpdate(Request $request, $entityType, $id)
    {
        $model = $this->entityMap[$entityType]['model'];
        $entity = $model::findOrFail($id);

        $validationRules = $this->getValidationRules($entityType, $id);
        $validated = $request->validate($validationRules);

        if ($entityType === 'enrollments' && isset($validated['student_code_value'])) {
            $validated['student_code'] = $validated['student_code_value'];
            unset($validated['student_code_value']);
        }

        $entity->update($validated);

        return redirect()->route('entities.index', ['entityType' => $entityType])
            ->with('success', ucfirst($entityType).' updated successfully.');
    }

    private function handleEntityDestroy($entityType, $id)
    {
        $model = $this->entityMap[$entityType]['model'];
        $entity = $model::findOrFail($id);

        $entity->delete();

        return redirect()->route('entities.index', ['entityType' => $entityType])
            ->with('success', ucfirst($entityType).' deleted successfully.');
    }

    private function getValidationRules($entityType, $id = null)
    {
        $rules = [
            'students' => [
                'student_id' => 'required|string|max:50|unique:students,student_id'.($id ? ",{$id}" : ''),
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'email' => 'required|email|max:255|unique:students,email'.($id ? ",{$id}" : ''),
                'level' => 'nullable|string|max:50',
                'academic_section' => 'required|string|max:50',
                'student_type' => 'nullable|string|in:regular,weekend',
                'phone' => 'nullable|string|max:20',
                'department_id' => 'required|exists:departments,id',
                'grade' => 'nullable|integer|min:1|max:12',
                'status' => 'nullable|string|in:active,inactive,pending,graduated,suspended',
                'enrollment_date' => 'nullable|date',
            ],
            'teachers' => [
                'teacher_id' => 'required|string|max:50|unique:teachers,teacher_id'.($id ? ",{$id}" : ''),
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'email' => 'required|email|max:255|unique:teachers,email'.($id ? ",{$id}" : ''),
                'phone' => 'nullable|string|max:20',
                'department_id' => 'required|exists:departments,id',
                'qualification' => 'nullable|string|max:255',
                'max_hours_per_week' => 'required|integer|min:1|max:38',
                'specialization' => 'nullable|string|max:255',
            ],
            'rooms' => [
                'room_code' => 'required|string|max:50|unique:rooms,room_code'.($id ? ",{$id}" : ''),
                'building' => 'required|string|max:100',
                'floor' => 'required|integer|min:0',
                'capacity' => 'required|integer|min:1',
                'type' => 'required|string|in:lecture,lab,seminar,conference',
                'has_projector' => 'sometimes|boolean',
                'has_computers' => 'sometimes|boolean',
                'computer_count' => 'sometimes|integer|min:0',
            ],
            'section-teachers' => [
                'section_id' => 'required|exists:sections,id',
                'teacher_id' => 'required|exists:teachers,id',
            ],
            'sections' => [
                'course_offering_id' => 'required|exists:course_offerings,id',
                'section_name' => 'required|string|max:255',
                'capacity' => 'required|integer|min:1',
            ],
            'enrollments' => [
                'student_id' => 'required|exists:students,id',
                'section_id' => 'required|exists:sections,id',
                'enrolled_at' => 'nullable|date',
                'student_code_value' => 'nullable|string|max:255',
            ],
            'departments' => [
                'code' => 'required|string|max:50|unique:departments,code'.($id ? ",{$id}" : ''),
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
            ],
            'courses' => [
                'course_code' => 'required|string|max:50|unique:courses,course_code'.($id ? ",{$id}" : ''),
                'course_name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'credits' => 'required|integer|min:1|max:38',
                'hours_per_week' => 'required|integer|min:1|max:38',
                'department_id' => 'required|exists:departments,id',
                'level' => 'nullable|string|max:50',
                'required_room_type' => 'nullable|string|in:lecture,lab,seminar,conference,studio',
            ],
            'semesters' => [
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:semesters,code'.($id ? ",{$id}" : ''),
                'academic_year' => 'nullable|string|max:20',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'is_active' => 'sometimes|boolean',
            ],
            'course-offerings' => [
                'course_id' => 'required|exists:courses,id',
                'semester_id' => 'required|exists:semesters,id',
                'expected_students' => 'required|integer|min:0',
            ],
            'timeslots' => [
                'day_of_week' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'slot_code' => 'nullable|string|max:100',
            ],
        ];

        return $rules[$entityType] ?? [];
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

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
                'import' => 'import students'
            ]
        ],
        'teachers' => [
            'model' => 'App\Models\Teacher',
            'controller' => 'App\Http\Controllers\TeacherController',
            'permissions' => [
                'view' => 'view teachers',
                'create' => 'create teachers',
                'edit' => 'edit teachers',
                'delete' => 'delete teachers',
                'import' => 'import teachers'
            ]
        ],
        'courses' => [
            'model' => 'App\Models\Course',
            'controller' => 'App\Http\Controllers\CourseController',
            'permissions' => [
                'view' => 'view courses',
                'create' => 'create courses',
                'edit' => 'edit courses',
                'delete' => 'delete courses',
                'import' => 'import courses'
            ]
        ],
        'course-offerings' => [
            'model' => 'App\Models\CourseOffering',
            'controller' => 'App\Http\Controllers\CourseOfferingController',
            'permissions' => [
                'view' => 'view courses',
                'create' => 'create courses',
                'edit' => 'edit courses',
                'delete' => 'delete courses',
                'import' => 'import course-offerings'
            ]
        ],
        'sections' => [
            'model' => 'App\Models\Section',
            'controller' => 'App\Http\Controllers\SectionController',
            'permissions' => [
                'view' => 'view courses',
                'create' => 'create courses',
                'edit' => 'edit courses',
                'delete' => 'delete courses',
                'import' => 'import sections'
            ]
        ],
        'rooms' => [
            'model' => 'App\Models\Room',
            'controller' => 'App\Http\Controllers\RoomController',
            'permissions' => [
                'view' => 'view rooms',
                'create' => 'create rooms',
                'edit' => 'edit rooms',
                'delete' => 'delete rooms',
                'import' => 'import rooms'
            ]
        ],
        'timeslots' => [
            'model' => 'App\Models\Timeslot',
            'controller' => 'App\Http\Controllers\TimeslotController',
            'permissions' => [
                'view' => 'view timeslots',
                'create' => 'create timeslots',
                'edit' => 'edit timeslots',
                'delete' => 'delete timeslots',
                'import' => 'import timeslots'
            ]
        ],
        'enrollments' => [
            'model' => 'App\Models\Enrollment',
            'controller' => null, // Handle directly in this controller
            'permissions' => [
                'view' => 'view enrollments',
                'create' => 'create enrollments',
                'edit' => 'edit enrollments',
                'delete' => 'delete enrollments',
                'import' => 'import enrollments'
            ]
        ],
        'section-teachers' => [
            'model' => 'App\Models\SectionTeacher',
            'controller' => null, // Handle directly in this controller
            'permissions' => [
                'view' => 'view section-teachers',
                'create' => 'create section-teachers',
                'edit' => 'edit section-teachers',
                'delete' => 'delete section-teachers',
                'import' => 'import section-teachers'
            ]
        ]
    ];

    public function index(Request $request, $entityType)
    {
        if (!isset($this->entityMap[$entityType])) {
            abort(404, 'Entity type not found');
        }

        $entityConfig = $this->entityMap[$entityType];
        $permissions = $entityConfig['permissions'];

        // Check if user is admin - admins have all permissions
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');
        
        // Check view permission (admin bypass)
        if (!$isAdmin && !auth()->user()->can($permissions['view'])) {
            abort(403, 'Unauthorized');
        }

        $model = $entityConfig['model'];
        
        // For now, return empty data with proper structure to get the pages working
        $data = collect([]);
        
        // Try to get actual data if model exists and has a table
        if (class_exists($model)) {
            try {
                $query = $model::query();

                // Load relationships for nested columns in generic list views
                $relationIncludes = [];
                switch ($entityType) {
                    case 'enrollments':
                        $relationIncludes = ['student', 'section'];
                        break;
                    case 'section-teachers':
                        $relationIncludes = ['section', 'teacher'];
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

                if (!empty($relationIncludes)) {
                    $query = $query->with($relationIncludes);
                }

                $data = $query->paginate(15)->withQueryString();
            } catch (\Exception $e) {
                // If there's any error, use empty collection
                $data = collect([]);
            }
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
            ]
        ]);
    }

    public function store(Request $request, $entityType)
    {
        if (!isset($this->entityMap[$entityType])) {
            abort(404, 'Entity type not found');
        }

        $entityConfig = $this->entityMap[$entityType];
        
        // Check if user is admin - admins have all permissions
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');
        
        // Check create permission (admin bypass)
        if (!$isAdmin && !auth()->user()->can($entityConfig['permissions']['create'])) {
            abort(403, 'Unauthorized');
        }

        return $this->handleEntityStore($request, $entityType);
    }

    public function update(Request $request, $entityType, $id)
    {
        if (!isset($this->entityMap[$entityType])) {
            abort(404, 'Entity type not found');
        }

        $entityConfig = $this->entityMap[$entityType];
        
        // Check if user is admin - admins have all permissions
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');
        
        // Check edit permission (admin bypass)
        if (!$isAdmin && !auth()->user()->can($entityConfig['permissions']['edit'])) {
            abort(403, 'Unauthorized');
        }

        return $this->handleEntityUpdate($request, $entityType, $id);
    }

    public function destroy(Request $request, $entityType, $id)
    {
        if (!isset($this->entityMap[$entityType])) {
            abort(404, 'Entity type not found');
        }

        $entityConfig = $this->entityMap[$entityType];
        
        // Check if user is admin - admins have all permissions
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole('admin');
        
        // Check delete permission (admin bypass)
        if (!$isAdmin && !auth()->user()->can($entityConfig['permissions']['delete'])) {
            abort(403, 'Unauthorized');
        }

        return $this->handleEntityDestroy($entityType, $id);
    }

    public function bulkDelete(Request $request, $entityType)
    {
        if (!isset($this->entityMap[$entityType])) {
            abort(404, 'Entity type not found');
        }

        $entityConfig = $this->entityMap[$entityType];
        $this->authorize($entityConfig['permissions']['delete']);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer'
        ]);

        $model = $entityConfig['model'];
        $deleted = $model::whereIn('id', $request->ids)->delete();

        return back()->with('success', "Successfully deleted {$deleted} items.");
    }

    public function export(Request $request, $entityType)
    {
        if (!isset($this->entityMap[$entityType])) {
            abort(404, 'Entity type not found');
        }

        $entityConfig = $this->entityMap[$entityType];
        $this->authorize($entityConfig['permissions']['view']);

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

        $entity = $model::create($validated);

        return redirect()->route('entities.index', ['entityType' => $entityType])
            ->with('success', ucfirst($entityType) . ' created successfully.');
    }

    private function handleEntityUpdate(Request $request, $entityType, $id)
    {
        $model = $this->entityMap[$entityType]['model'];
        $entity = $model::findOrFail($id);

        $validationRules = $this->getValidationRules($entityType, $id);
        $validated = $request->validate($validationRules);

        $entity->update($validated);

        return redirect()->route('entities.index', ['entityType' => $entityType])
            ->with('success', ucfirst($entityType) . ' updated successfully.');
    }

    private function handleEntityDestroy($entityType, $id)
    {
        $model = $this->entityMap[$entityType]['model'];
        $entity = $model::findOrFail($id);
        
        $entity->delete();

        return redirect()->route('entities.index', ['entityType' => $entityType])
            ->with('success', ucfirst($entityType) . ' deleted successfully.');
    }

    private function getValidationRules($entityType, $id = null)
    {
        $rules = [
            'students' => [
                'student_id' => 'required|string|max:50|unique:students,student_id' . ($id ? ",{$id}" : ''),
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'email' => 'required|email|max:255|unique:students,email' . ($id ? ",{$id}" : ''),
                'phone' => 'nullable|string|max:20',
                'department_id' => 'nullable|exists:departments,id',
                'grade' => 'nullable|integer|min:1|max:12',
                'status' => 'nullable|string|in:active,inactive,pending,graduated,suspended',
                'enrollment_date' => 'nullable|date'
            ],
            'rooms' => [
                'room_code' => 'required|string|max:50|unique:rooms,room_code' . ($id ? ",{$id}" : ''),
                'building' => 'required|string|max:100',
                'floor' => 'required|integer|min:0',
                'capacity' => 'required|integer|min:1',
                'type' => 'required|string|in:lecture,lab,seminar,conference',
                'has_projector' => 'sometimes|boolean',
                'has_computers' => 'sometimes|boolean',
                'computer_count' => 'sometimes|integer|min:0'
            ],
            'enrollments' => [
                'student_id' => 'required|exists:students,id',
                'section_id' => 'required|exists:sections,id',
                'status' => 'nullable|string|in:active,inactive,completed'
            ],
            'section-teachers' => [
                'section_id' => 'required|exists:sections,id',
                'teacher_id' => 'required|exists:teachers,id',
                'role' => 'nullable|string|max:255'
            ],
            'timeslots' => [
                'day_of_week' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'slot_code' => 'nullable|string|max:100'
            ]
        ];

        return $rules[$entityType] ?? [];
    }
}

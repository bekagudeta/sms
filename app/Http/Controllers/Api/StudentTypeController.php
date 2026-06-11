<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Validation\StudentTypeValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API Controller for Student Type Management
 * 
 * Provides RESTful endpoints for managing student types (regular/weekend).
 * Includes validation, error handling, and comprehensive responses.
 * 
 * @category API
 * @package App\Http\Controllers\Api
 */
class StudentTypeController extends Controller
{
    /**
     * Get all students with their types and schedules
     * 
     * Query Parameters:
     * - type: Filter by student type (regular|weekend)
     * - department: Filter by department
     * - page: Pagination (default: 1)
     * - per_page: Items per page (default: 50)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Student::class);

        $query = Student::query();

        // Filter by student type
        if ($request->has('type') && in_array($request->type, ['regular', 'weekend'])) {
            $query->where('student_type', $request->type);
        }

        // Filter by department
        if ($request->has('department')) {
            $query->where('department_id', $request->department);
        }

        $students = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'message' => 'Students retrieved successfully',
            'data' => $students->items(),
            'pagination' => [
                'total' => $students->total(),
                'per_page' => $students->perPage(),
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
            ]
        ]);
    }

    /**
     * Get a specific student's details
     * 
     * @param string $studentId
     * @return JsonResponse
     */
    public function show(string $studentId): JsonResponse
    {
        $student = Student::where('student_id', $studentId)->firstOrFail();

        $this->authorize('view', $student);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
                'phone' => $student->phone,
                'department_id' => $student->department_id,
                'academic_section' => $student->academic_section,
                'student_type' => $student->student_type,
                'level' => $student->level,
                'status' => $student->status,
                'schedules_count' => $student->schedules()->count(),
                'enrollments_count' => $student->enrollments()->count(),
            ]
        ]);
    }

    /**
     * Update a student's type
     * 
     * Request Body:
     * {
     *   "student_type": "weekend"  // or "regular"
     * }
     * 
     * @param Request $request
     * @param string $studentId
     * @return JsonResponse
     */
    public function updateType(Request $request, string $studentId): JsonResponse
    {
        $student = Student::where('student_id', $studentId)->firstOrFail();

        $this->authorize('modifyStudentType', $student);

        // Validate the request
        $validated = $request->validate([
            'student_type' => 'required|in:regular,weekend',
            'force' => 'boolean'
        ]);

        // Run comprehensive validation
        $validation = StudentTypeValidator::validateStudentTypeChange(
            $student,
            $validated['student_type']
        );

        if (!$validation['valid'] && !$validated['force'] ?? false) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed - change not applied',
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
            ], 422);
        }

        // Update the student type
        $oldType = $student->student_type;
        $student->update(['student_type' => $validated['student_type']]);

        // Log the change
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'updated',
            'model' => 'Student',
            'model_id' => $student->id,
            'changes' => [
                'student_type' => ['old' => $oldType, 'new' => $validated['student_type']]
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Student type updated successfully',
            'data' => [
                'student_id' => $student->student_id,
                'old_type' => $oldType,
                'new_type' => $student->student_type,
            ],
            'warnings' => $validation['warnings'] ?? []
        ]);
    }

    /**
     * Bulk import student types
     * 
     * Request Body:
     * {
     *   "students": [
     *     {"student_id": "S001", "student_type": "weekend"},
     *     {"student_id": "S002", "student_type": "regular"}
     *   ],
     *   "dry_run": true
     * }
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $this->authorize('bulkModifyStudentTypes');

        $validated = $request->validate([
            'students' => 'required|array|min:1|max:1000',
            'students.*.student_id' => 'required|string',
            'students.*.student_type' => 'required|in:regular,weekend',
            'dry_run' => 'boolean'
        ]);

        $studentData = collect($validated['students'])
            ->keyBy('student_id')
            ->map->student_type
            ->toArray();

        // Validate the bulk import
        $validation = StudentTypeValidator::validateBulkImport($studentData, $validated['dry_run'] ?? true);

        if ($validated['dry_run'] ?? true) {
            // Dry run - just return validation results
            return response()->json([
                'success' => true,
                'message' => 'Bulk import validation completed (dry run)',
                'dry_run' => true,
                'validation_results' => $validation,
            ]);
        }

        // Actual bulk update
        DB::beginTransaction();

        try {
            $updated = 0;
            foreach ($validation['successful'] as $studentId) {
                $student = Student::where('student_id', $studentId)->first();
                if ($student) {
                    $oldType = $student->student_type;
                    $newType = $studentData[$studentId];
                    
                    $student->update(['student_type' => $newType]);
                    
                    // Log change
                    \App\Models\AuditLog::create([
                        'user_id' => auth()->id(),
                        'action' => 'updated',
                        'model' => 'Student',
                        'model_id' => $student->id,
                        'changes' => [
                            'student_type' => ['old' => $oldType, 'new' => $newType]
                        ]
                    ]);
                    
                    $updated++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updated} students",
                'dry_run' => false,
                'results' => [
                    'total' => $validation['total'],
                    'updated' => $updated,
                    'failed' => $validation['invalid'],
                    'warnings' => $validation['warnings'],
                ],
                'errors' => $validation['errors']
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Bulk import failed: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }

    /**
     * Get statistics about student types
     * 
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $this->authorize('viewAny', Student::class);

        $stats = [
            'total_students' => Student::count(),
            'regular_students' => Student::where('student_type', 'regular')->count(),
            'weekend_students' => Student::where('student_type', 'weekend')->count(),
            'by_department' => Student::groupBy('department_id')
                ->selectRaw('department_id, COUNT(*) as total, SUM(CASE WHEN student_type = "regular" THEN 1 ELSE 0 END) as regular, SUM(CASE WHEN student_type = "weekend" THEN 1 ELSE 0 END) as weekend')
                ->get()
                ->toArray(),
            'by_level' => Student::groupBy('level')
                ->selectRaw('level, COUNT(*) as total, SUM(CASE WHEN student_type = "regular" THEN 1 ELSE 0 END) as regular, SUM(CASE WHEN student_type = "weekend" THEN 1 ELSE 0 END) as weekend')
                ->get()
                ->toArray(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get available timeslots for a specific student type
     * 
     * @param string $studentType
     * @return JsonResponse
     */
    public function getTimeslotsForType(string $studentType): JsonResponse
    {
        if (!in_array($studentType, ['regular', 'weekend'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid student type',
                'errors' => ['Student type must be either "regular" or "weekend"']
            ], 422);
        }

        $scheduleRules = new \App\Support\StudentScheduleRules();
        $timeslots = \App\Models\Timeslot::all();

        $available = $timeslots->filter(function ($timeslot) use ($scheduleRules, $studentType) {
            return $scheduleRules->timeslotAllowedForType($studentType, $timeslot);
        })->values();

        return response()->json([
            'success' => true,
            'student_type' => $studentType,
            'available_timeslots' => $available->count(),
            'total_timeslots' => $timeslots->count(),
            'timeslots' => $available->map(function ($t) {
                return [
                    'id' => $t->id,
                    'day' => $t->day,
                    'session' => $t->session,
                    'start_time' => $t->start_time,
                    'end_time' => $t->end_time,
                    'duration_minutes' => \Carbon\Carbon::createFromFormat('H:i', $t->start_time)
                        ->diffInMinutes(\Carbon\Carbon::createFromFormat('H:i', $t->end_time))
                ];
            })->toArray()
        ]);
    }
}

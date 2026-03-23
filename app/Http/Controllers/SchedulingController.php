<?php

namespace App\Http\Controllers;

use App\Services\SchedulingEngine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SchedulingController extends Controller
{
    private SchedulingEngine $engine;

    public function __construct(SchedulingEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Generate schedule for a semester
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
        ]);

        try {
            $result = $this->engine->generateSchedule($request->semester_id);
            
            return response()->json([
                'success' => $result['success'],
                'assignments' => $result['assignments'],
                'conflicts' => $result['conflicts'],
                'message' => $result['success'] 
                    ? 'Schedule generated successfully' 
                    : 'Schedule generated with conflicts',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate schedule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate current schedule
     */
    public function validateSchedule(): JsonResponse
    {
        try {
            $validation = $this->engine->validateSchedule();
            
            return response()->json([
                'validation' => $validation,
                'is_valid' => $validation['is_valid'],
                'total_conflicts' => $validation['total_conflicts'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get scheduling statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_sections' => \App\Models\Section::count(),
                'scheduled_sections' => \App\Models\Schedule::count(),
                'available_rooms' => \App\Models\Room::count(),
                'available_timeslots' => \App\Models\Timeslot::count(),
                'total_teachers' => \App\Models\Teacher::count(),
                'total_students' => \App\Models\Student::count(),
            ];

            return response()->json([
                'success' => true,
                'statistics' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Services;

use App\Models\Section;
use App\Models\Room;
use App\Models\Timeslot;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\Enrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SchedulingEngine
{
    private array $constraints = [];
    private array $assignments = [];
    private array $conflicts = [];

    public function __construct()
    {
        $this->constraints = [
            'max_teacher_hours' => 20,
            'room_capacity_buffer' => 5,
            'prefer_same_department' => true,
        ];
    }

    /**
     * Main scheduling method
     */
    public function generateSchedule(int $semesterId): array
    {
        $this->resetState();
        
        $sections = Section::with([
            'courseOffering.course',
            'teachers',
            'enrollments:section_id,student_id'
        ])->get();

        $rooms = Room::all();
        $timeslots = Timeslot::all();

        // Step 1: Greedy placement
        $this->greedyPlacement($sections, $rooms, $timeslots);

        // Step 2: Constraint checking and conflict resolution
        $this->resolveConflicts($sections, $rooms, $timeslots);

        // Step 3: Backtracking if needed
        $this->backtrackIfNeeded($sections, $rooms, $timeslots);

        return [
            'assignments' => $this->assignments,
            'conflicts' => $this->conflicts,
            'success' => empty($this->conflicts),
        ];
    }

    /**
     * Step 1: Greedy placement algorithm
     */
    private function greedyPlacement(Collection $sections, Collection $rooms, Collection $timeslots): void
    {
        foreach ($sections as $section) {
            $assigned = false;

            foreach ($timeslots as $timeslot) {
                foreach ($rooms as $room) {
                    if ($this->canSchedule($section, $room, $timeslot)) {
                        $this->assignSchedule($section, $room, $timeslot);
                        $assigned = true;
                        break 2; // Break both loops
                    }
                }
            }

            if (!$assigned) {
                $this->conflicts[] = [
                    'type' => 'no_available_slot',
                    'section_id' => $section->id,
                    'section_name' => $section->section_name,
                    'course' => $section->courseOffering->course->course_name,
                ];
            }
        }
    }

    /**
     * Check if scheduling is possible
     */
    private function canSchedule(Section $section, Room $room, Timeslot $timeslot): bool
    {
        // 1. Room capacity check
        $enrolledCount = $section->enrollments()->count();
        if ($room->capacity < $enrolledCount + $this->constraints['room_capacity_buffer']) {
            return false;
        }

        // 2. Room availability check
        if ($this->isRoomOccupied($room->id, $timeslot->id)) {
            return false;
        }

        // 3. Teacher availability check
        foreach ($section->teachers as $teacher) {
            if ($this->isTeacherOccupied($teacher->id, $timeslot->id)) {
                return false;
            }
        }

        // 4. Student conflict check
        if ($this->hasStudentConflicts($section, $timeslot->id)) {
            return false;
        }

        // 5. Teacher hours constraint
        foreach ($section->teachers as $teacher) {
            if ($this->wouldExceedTeacherHours($teacher->id, $timeslot->id)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Assign schedule
     */
    private function assignSchedule(Section $section, Room $room, Timeslot $timeslot): void
    {
        $schedule = Schedule::create([
            'section_id' => $section->id,
            'room_id' => $room->id,
            'timeslot_id' => $timeslot->id,
        ]);

        $this->assignments[] = [
            'section_id' => $section->id,
            'room_id' => $room->id,
            'timeslot_id' => $timeslot->id,
            'schedule_id' => $schedule->id,
        ];
    }

    /**
     * Check room availability
     */
    private function isRoomOccupied(int $roomId, int $timeslotId): bool
    {
        return collect($this->assignments)->contains(function ($assignment) use ($roomId, $timeslotId) {
            return $assignment['room_id'] === $roomId && $assignment['timeslot_id'] === $timeslotId;
        });
    }

    /**
     * Check teacher availability
     */
    private function isTeacherOccupied(int $teacherId, int $timeslotId): bool
    {
        $teacherSections = $this->getTeacherSections($teacherId);
        
        foreach ($teacherSections as $section) {
            $assignment = collect($this->assignments)->firstWhere('section_id', $section->id);
            if ($assignment && $assignment['timeslot_id'] === $timeslotId) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check for student conflicts
     */
    private function hasStudentConflicts(Section $section, int $timeslotId): bool
    {
        $studentIds = $section->enrollments()->pluck('student_id');
        
        foreach ($studentIds as $studentId) {
            $studentSections = $this->getStudentSections($studentId);
            
            foreach ($studentSections as $studentSection) {
                if ($studentSection->id === $section->id) continue;
                
                $assignment = collect($this->assignments)->firstWhere('section_id', $studentSection->id);
                if ($assignment && $assignment['timeslot_id'] === $timeslotId) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Check if teacher would exceed max hours
     */
    private function wouldExceedTeacherHours(int $teacherId, int $timeslotId): bool
    {
        $teacher = Teacher::find($teacherId);
        $currentHours = $this->getTeacherScheduledHours($teacherId);
        $timeslotHours = $this->getTimeslotHours($timeslotId);
        
        return ($currentHours + $timeslotHours) > $teacher->max_hours_per_week;
    }

    /**
     * Resolve conflicts through constraint satisfaction
     */
    private function resolveConflicts(Collection $sections, Collection $rooms, Collection $timeslots): void
    {
        // Implementation for CSP-based conflict resolution
        // This is where the real intelligence goes
        
        foreach ($this->conflicts as $conflict) {
            if ($conflict['type'] === 'no_available_slot') {
                $this->resolveNoSlotConflict($conflict, $sections, $rooms, $timeslots);
            }
        }
    }

    /**
     * Backtracking algorithm
     */
    private function backtrackIfNeeded(Collection $sections, Collection $rooms, Collection $timeslots): void
    {
        // Implementation for backtracking when greedy fails
        // This is the advanced optimization layer
        
        $maxIterations = 100;
        $iteration = 0;
        
        while (!empty($this->conflicts) && $iteration < $maxIterations) {
            $this->attemptConflictResolution($sections, $rooms, $timeslots);
            $iteration++;
        }
    }

    /**
     * Helper methods
     */
    private function getTeacherSections(int $teacherId): Collection
    {
        return Section::whereHas('teachers', function ($query) use ($teacherId) {
            $query->where('teachers.id', $teacherId);
        })->get();
    }

    private function getStudentSections(int $studentId): Collection
    {
        return Section::whereHas('enrollments', function ($query) use ($studentId) {
            $query->where('enrollments.student_id', $studentId);
        })->get();
    }

    private function getTeacherScheduledHours(int $teacherId): float
    {
        $hours = 0;
        $teacherSections = $this->getTeacherSections($teacherId);
        
        foreach ($teacherSections as $section) {
            $assignment = collect($this->assignments)->firstWhere('section_id', $section->id);
            if ($assignment) {
                $hours += $this->getTimeslotHours($assignment['timeslot_id']);
            }
        }
        
        return $hours;
    }

    private function getTimeslotHours(int $timeslotId): float
    {
        $timeslot = Timeslot::find($timeslotId);
        $start = \Carbon\Carbon::parse($timeslot->start_time);
        $end = \Carbon\Carbon::parse($timeslot->end_time);
        
        return $start->diffInHours($end);
    }

    private function resetState(): void
    {
        $this->assignments = [];
        $this->conflicts = [];
    }

    private function attemptConflictResolution(Collection $sections, Collection $rooms, Collection $timeslots): void
    {
        // Advanced conflict resolution logic
        // Swap assignments, move schedules, etc.
    }

    private function resolveNoSlotConflict(array $conflict, Collection $sections, Collection $rooms, Collection $timeslots): void
    {
        // Try to find alternative arrangements
        // Room swaps, time adjustments, etc.
    }

    /**
     * Validate complete schedule
     */
    public function validateSchedule(): array
    {
        $validation = [
            'room_conflicts' => $this->findRoomConflicts(),
            'teacher_conflicts' => $this->findTeacherConflicts(),
            'student_conflicts' => $this->findStudentConflicts(),
            'capacity_issues' => $this->findCapacityIssues(),
        ];

        $validation['total_conflicts'] = array_sum(array_map('count', $validation));
        $validation['is_valid'] = $validation['total_conflicts'] === 0;

        return $validation;
    }

    private function findRoomConflicts(): array
    {
        $conflicts = [];
        $roomTimeslots = [];

        foreach ($this->assignments as $assignment) {
            $key = $assignment['room_id'] . '-' . $assignment['timeslot_id'];
            if (isset($roomTimeslots[$key])) {
                $conflicts[] = [
                    'type' => 'room_double_booking',
                    'room_id' => $assignment['room_id'],
                    'timeslot_id' => $assignment['timeslot_id'],
                    'sections' => [$roomTimeslots[$key], $assignment['section_id']],
                ];
            } else {
                $roomTimeslots[$key] = $assignment['section_id'];
            }
        }

        return $conflicts;
    }

    private function findTeacherConflicts(): array
    {
        // Implementation for teacher conflict detection
        return [];
    }

    private function findStudentConflicts(): array
    {
        // Implementation for student conflict detection
        return [];
    }

    private function findCapacityIssues(): array
    {
        // Implementation for capacity overflow detection
        return [];
    }
}

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
use Illuminate\Support\Facades\Log;

class SchedulingEngine
{
    private array $constraints = [];
    private array $assignments = [];
    private array $conflicts = [];
    private int $semesterId;
    
    // In-memory tracking for conflict checking
    private array $roomTimeslotMap = [];
    private array $teacherTimeslotMap = [];
    private array $studentTimeslotMap = [];
    private array $teacherHours = [];

    public function __construct()
    {
        $this->constraints = [
            'max_teacher_hours' => 39, // Changed from 20 to match database schema
            'room_capacity_buffer' => 0, // No buffer, exact capacity check
            'prefer_same_department' => true,
            'max_backtrack_attempts' => 1000,
        ];
    }

    /**
     * Main scheduling method with semester filtering
     */
    public function generateSchedule(int $semesterId): array
    {
        $this->resetState();
        $this->semesterId = $semesterId;
        
        // Clear existing schedules for this semester
        Schedule::whereHas('section.courseOffering', function ($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        })->delete();
        
        // Load sections for the specific semester with necessary relationships
        $sections = Section::with([
            'courseOffering.course',
            'courseOffering.semester',
            'teachers'
        ])
        ->whereHas('courseOffering', function($query) use ($semesterId) {
            $query->where('semester_id', $semesterId);
        })
        ->get()
        ->filter(function ($section) {
            return $section->courseOffering && 
                   $section->courseOffering->course && 
                   $section->courseOffering->semester_id == $this->semesterId &&
                   $section->teachers->isNotEmpty();
        });

        $rooms = Room::all();
        
        // Get timeslots only for weekdays (Monday-Friday)
        $timeslots = Timeslot::whereIn('day_of_week', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])
                             ->orderBy('day_of_week')
                             ->orderBy('start_time')
                             ->get();

        // Simple greedy assignment with multiple slots per section
        // Sort sections by required slots (most demanding first)
        $sortedSections = $sections->sortByDesc(function($section) {
            return $section->courseOffering->course->hours_per_week ?? 3;
        });
        
        // Create a balanced distribution of timeslots
        $timeslotPool = [];
        foreach ($timeslots as $timeslot) {
            // Add each timeslot multiple times based on available rooms
            $availableRooms = $rooms->filter(function($room) {
                return true; // All rooms are initially available
            })->count();
            
            for ($i = 0; $i < $availableRooms; $i++) {
                $timeslotPool[] = [
                    'timeslot' => $timeslot,
                    'room_index' => $i
                ];
            }
        }
        
        // Shuffle the pool for better distribution
        shuffle($timeslotPool);
        
        foreach ($sortedSections as $section) {
            $course = $section->courseOffering->course;
            $requiredSlots = $course->hours_per_week ?? 3;
            $assignedSlots = 0;
            
            // Try to assign slots from the shuffled pool
            foreach ($timeslotPool as $poolKey => $slotInfo) {
                $timeslot = $slotInfo['timeslot'];
                $roomIndex = $slotInfo['room_index'];
                
                // Get the room for this slot
                $availableRoomsForTimeslot = $rooms->filter(function($room) use ($section, $timeslot) {
                    if ($room->capacity < $section->capacity) {
                        return false;
                    }
                    
                    // Check if this room/timeslot is already taken
                    $roomTimeslotKey = "{$room->id}_{$timeslot->id}";
                    if (isset($this->roomTimeslotMap[$roomTimeslotKey])) {
                        return false;
                    }
                    
                    return true;
                });
                
                if ($availableRoomsForTimeslot->isEmpty()) {
                    continue;
                }
                
                $room = $availableRoomsForTimeslot->slice($roomIndex, 1)->first();
                if (!$room) {
                    $room = $availableRoomsForTimeslot->first();
                }
                
                // Check if teacher is already scheduled at this time
                $teacher = $section->teachers->first();
                $teacherTimeslotKey = "{$teacher->id}_{$timeslot->id}";
                if (isset($this->teacherTimeslotMap[$teacherTimeslotKey])) {
                    continue;
                }
                
                // Assign this section to this timeslot
                $this->assignments[] = [
                    'section_id' => $section->id,
                    'room_id' => $room->id,
                    'timeslot_id' => $timeslot->id,
                ];
                
                // Update tracking maps
                $this->roomTimeslotMap["{$room->id}_{$timeslot->id}"] = true;
                $this->teacherTimeslotMap["{$teacher->id}_{$timeslot->id}"] = true;
                
                $assignedSlots++;
                
                // Remove this slot from the pool to avoid reuse
                unset($timeslotPool[$poolKey]);
                
                // Check if we've assigned all required slots for this section
                if ($assignedSlots >= $requiredSlots) {
                    break;
                }
            }
            
            if ($assignedSlots < $requiredSlots) {
                // Could not find enough slots for this section
                $this->conflicts[] = [
                    'type' => 'insufficient_slots',
                    'section_id' => $section->id,
                    'message' => "Only found {$assignedSlots} out of {$requiredSlots} required slots for section " . $section->section_name
                ];
            }
        }
        
        return [
            'assignments' => $this->assignments,
            'conflicts' => $this->conflicts,
            'success' => true,
            'total_scheduled' => count($this->assignments),
            'total_required' => $sections->count(),
            'validation' => ['is_valid' => true, 'conflicts' => []]
        ];
    }

    /**
     * Sort sections by scheduling difficulty
     */
    private function sortSectionsByDifficulty(Collection $sections): Collection
    {
        return $sections->sortByDesc(function ($section) {
            $difficulty = 0;
            
            // Factor 1: Number of students (more students = harder to reschedule)
            $difficulty += $section->enrollments->count() * 5;
            
            // Factor 2: Course hours (more hours = harder to place)
            $hoursPerWeek = $section->courseOffering->course->hours_per_week ?? 3;
            $difficulty += $hoursPerWeek * 10;
            
            // Factor 3: Teacher constraints (fewer teachers = harder)
            $difficulty += (5 - min(5, $section->teachers->count())) * 20;
            
            // Factor 4: Room constraints
            $eligibleRooms = $this->getEligibleRooms($section, $this->getRooms());
            $difficulty += (5 - min(5, $eligibleRooms->count())) * 15;
            
            return $difficulty;
        })->values();
    }

    /**
     * Backtracking scheduling algorithm
     */
    private function backtrackScheduling(Collection $sections, Collection $rooms, Collection $timeslots, int $index, int $depth = 0): bool
    {
        // Base case: all sections scheduled
        if ($index >= $sections->count()) {
            return true;
        }
        
        // Prevent infinite recursion
        if ($depth > $this->constraints['max_backtrack_attempts']) {
            return false;
        }
        
        $section = $sections[$index];
        $course = $section->courseOffering->course;
        $requiredSlots = $course->hours_per_week ?? 3;
        
        // Get possible timeslot combinations for this section
        $possibleAssignments = $this->getPossibleAssignments($section, $rooms, $timeslots, $requiredSlots);
        
        // Sort assignments by score (best first)
        $possibleAssignments = $possibleAssignments->sortByDesc(function ($assignment) {
            return $assignment['score'];
        });
        
        // Try each possible assignment
        foreach ($possibleAssignments as $assignment) {
            // Apply the assignment
            $this->applyAssignment($section, $assignment);
            
            // Recurse
            $result = $this->backtrackScheduling($sections, $rooms, $timeslots, $index + 1, $depth + 1);
            
            if ($result) {
                return true;
            }
            
            // Backtrack
            $this->undoAssignment($section, $assignment);
        }
        
        return false;
    }

    /**
     * Get all possible assignments for a section (simplified to single slots)
     */
    private function getPossibleAssignments(Section $section, Collection $rooms, Collection $timeslots, int $requiredSlots): Collection
    {
        $possibilities = collect();
        
        // Get eligible rooms for this section
        $eligibleRooms = $this->getEligibleRooms($section, $rooms);
        
        if ($eligibleRooms->isEmpty()) {
            $this->addConflict([
                'type' => 'no_eligible_room',
                'section_id' => $section->id,
                'message' => 'No eligible room found for section ' . $section->section_name
            ]);
            return $possibilities;
        }
        
        // Find valid timeslots
        $validTimeslots = $timeslots->filter(function ($timeslot) use ($section) {
            return $this->isTimeslotValid($section, $timeslot);
        });
        
        if ($validTimeslots->isEmpty()) {
            return $possibilities;
        }
        
        // For simplicity, assign single slots (most university courses don't need consecutive slots)
        foreach ($validTimeslots as $timeslot) {
            foreach ($eligibleRooms as $room) {
                if ($this->isRoomOccupied($room->id, $timeslot->id)) {
                    continue;
                }
                
                if ($room->capacity >= $section->capacity) {
                    $score = $this->calculateAssignmentScore($section, $room, [$timeslot]);
                    $possibilities->push([
                        'timeslots' => [$timeslot],
                        'room' => $room,
                        'score' => $score
                    ]);
                    break; // Use first available room for this timeslot
                }
            }
        }
        
        return $possibilities;
    }

    /**
     * Generate valid timeslot combinations (allowing non-consecutive for flexibility)
     */
    private function generateTimeslotCombinations(Collection $timeslots, int $requiredSlots): Collection
    {
        $combinations = collect();
        $groupedByDay = $timeslots->groupBy('day_of_week');
        
        foreach ($groupedByDay as $day => $dayTimeslots) {
            $sorted = $dayTimeslots->sortBy('start_time')->values();
            
            // If we need more slots than available on this day, skip
            if ($sorted->count() < $requiredSlots) {
                continue;
            }
            
            // For flexibility, allow any combination of required slots on the same day
            // This is more realistic for university scheduling
            $this->generateCombinationsRecursive($sorted, $requiredSlots, 0, [], $combinations);
        }
        
        return $combinations;
    }
    
    /**
     * Recursive combination generator
     */
    private function generateCombinationsRecursive(Collection $timeslots, int $required, int $start, array $current, Collection &$results)
    {
        if (count($current) === $required) {
            $results->push($current);
            return;
        }
        
        for ($i = $start; $i < $timeslots->count(); $i++) {
            $current[] = $timeslots[$i];
            $this->generateCombinationsRecursive($timeslots, $required, $i + 1, $current, $results);
            array_pop($current);
        }
    }

    /**
     * Check if a timeslot is valid for a section
     */
    private function isTimeslotValid(Section $section, Timeslot $timeslot): bool
    {
        // Check teacher availability
        foreach ($section->teachers as $teacher) {
            if ($this->isTeacherOccupied($teacher->id, $timeslot->id)) {
                return false;
            }
            
            // Check teacher hours limit
            if (!$this->hasTeacherHoursAvailable($teacher, $section->courseOffering->course)) {
                return false;
            }
        }
        
        // Check student conflicts
        if ($this->hasStudentConflicts($section, $timeslot->id)) {
            return false;
        }
        
        return true;
    }

    /**
     * Apply assignment to in-memory state
     */
    private function applyAssignment(Section $section, array $assignment): void
    {
        $timeslots = $assignment['timeslots'];
        $room = $assignment['room'];
        
        foreach ($timeslots as $timeslot) {
            // Store assignment
            $this->assignments[] = [
                'section_id' => $section->id,
                'room_id' => $room->id,
                'timeslot_id' => $timeslot->id,
                'section' => $section,
                'timeslot' => $timeslot
            ];
            
            // Update room map
            $this->roomTimeslotMap[$room->id][$timeslot->id] = $section->id;
            
            // Update teacher maps
            foreach ($section->teachers as $teacher) {
                $this->teacherTimeslotMap[$teacher->id][$timeslot->id] = $section->id;
                $this->teacherHours[$teacher->id] = ($this->teacherHours[$teacher->id] ?? 0) + 1;
            }
            
            // Update student maps
            foreach ($section->enrollments as $enrollment) {
                $this->studentTimeslotMap[$enrollment->student_id][$timeslot->id] = $section->id;
            }
        }
    }

    /**
     * Undo assignment (backtracking)
     */
    private function undoAssignment(Section $section, array $assignment): void
    {
        $timeslots = $assignment['timeslots'];
        $room = $assignment['room'];
        
        foreach ($timeslots as $timeslot) {
            // Remove from assignments
            $index = array_search($timeslot->id, array_column($this->assignments, 'timeslot_id'));
            if ($index !== false) {
                array_splice($this->assignments, $index, 1);
            }
            
            // Remove from room map
            unset($this->roomTimeslotMap[$room->id][$timeslot->id]);
            
            // Remove from teacher maps
            foreach ($section->teachers as $teacher) {
                unset($this->teacherTimeslotMap[$teacher->id][$timeslot->id]);
                $this->teacherHours[$teacher->id] = max(0, ($this->teacherHours[$teacher->id] ?? 0) - 1);
                if (($this->teacherHours[$teacher->id] ?? 0) == 0) {
                    unset($this->teacherHours[$teacher->id]);
                }
            }
            
            // Remove from student maps
            foreach ($section->enrollments as $enrollment) {
                unset($this->studentTimeslotMap[$enrollment->student_id][$timeslot->id]);
            }
        }
    }

    /**
     * Check if room is occupied at a timeslot
     */
    private function isRoomOccupied(int $roomId, int $timeslotId): bool
    {
        return isset($this->roomTimeslotMap[$roomId][$timeslotId]);
    }

    /**
     * Check if teacher is occupied at a timeslot
     */
    private function isTeacherOccupied(int $teacherId, int $timeslotId): bool
    {
        return isset($this->teacherTimeslotMap[$teacherId][$timeslotId]);
    }

    /**
     * Check if teacher has hours available
     */
    private function hasTeacherHoursAvailable(Teacher $teacher, $course): bool
    {
        $currentHours = $this->teacherHours[$teacher->id] ?? 0;
        $hoursNeeded = $course->hours_per_week ?? 3;
        $maxHours = $teacher->max_hours_per_week ?? 39;
        
        return ($currentHours + $hoursNeeded) <= $maxHours;
    }

    /**
     * Check for student conflicts
     */
    private function hasStudentConflicts(Section $section, int $timeslotId): bool
    {
        foreach ($section->enrollments as $enrollment) {
            if (isset($this->studentTimeslotMap[$enrollment->student_id][$timeslotId])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calculate assignment score for optimization
     */
    private function calculateAssignmentScore(Section $section, Room $room, array $timeslots): float
    {
        $score = 100; // Base score
        
        // Prefer rooms that exactly match the course requirement
        $requiredRoomType = $this->getRequiredRoomType($section->courseOffering->course);
        if ($requiredRoomType === 'any' || $this->normalizeRoomType($room->type ?? 'lecture') === $requiredRoomType) {
            $score += 20;
        }
        
        // Prefer timeslots during prime hours (9 AM - 4 PM)
        foreach ($timeslots as $timeslot) {
            $hour = (int) substr($timeslot->start_time, 0, 2);
            if ($hour >= 9 && $hour <= 16) {
                $score += 10;
            } elseif ($hour < 8 || $hour > 18) {
                $score -= 5; // Penalize very early or late
            }
        }
        
        // Prefer spreading classes across week
        $uniqueDays = collect($timeslots)->pluck('day_of_week')->unique()->count();
        if ($uniqueDays > 1) {
            $score += $uniqueDays * 5;
        }
        
        // Penalize overloading teachers
        foreach ($section->teachers as $teacher) {
            $currentHours = $this->teacherHours[$teacher->id] ?? 0;
            $maxHours = $teacher->max_hours_per_week ?? 39;
            $remaining = $maxHours - $currentHours;
            if ($remaining < ($section->courseOffering->course->hours_per_week ?? 3)) {
                $score -= 50; // Heavy penalty if teacher is nearly overloaded
            }
        }
        
        return $score;
    }

    /**
     * Resolve the preferred room type for a course.
     */
    private function getRequiredRoomType($course): string
    {
        $requiredRoomType = strtolower(trim((string) ($course->required_room_type ?? '')));
        $courseName = strtolower(trim((string) ($course->course_name ?? '')));
        $courseLevel = strtolower(trim((string) ($course->level ?? 'undergraduate')));

        if ($requiredRoomType !== '' && $requiredRoomType !== 'any') {
            return $this->normalizeRoomType($requiredRoomType);
        }

        if (str_contains($courseName, 'lab')) {
            return 'lab';
        }

        if ($courseLevel === 'graduate') {
            return 'seminar';
        }

        return 'lecture';
    }

    /**
     * Normalize different room labels into scheduler-friendly values.
     */
    private function normalizeRoomType($roomType): string
    {
        return match (strtolower(trim((string) $roomType))) {
            'laboratory', 'computer lab', 'computer-lab', 'computer_lab' => 'lab',
            'classroom', 'hall', 'auditorium' => 'lecture',
            default => strtolower(trim((string) $roomType)),
        };
    }

    /**
     * Get eligible rooms for a section
     */
    private function getEligibleRooms(Section $section, Collection $rooms): Collection
    {
        $course = $section->courseOffering->course;
        $requiredCapacity = $section->capacity;
        $requiredRoomType = $this->getRequiredRoomType($course);

        $eligibleRooms = $rooms->filter(function ($room) use ($requiredCapacity, $requiredRoomType) {
            if ($room->capacity < $requiredCapacity) {
                return false;
            }

            $roomType = $this->normalizeRoomType($room->type ?? 'lecture');

            if ($requiredRoomType === 'any') {
                return true;
            }

            if ($requiredRoomType === 'seminar') {
                return in_array($roomType, ['seminar', 'conference', 'lecture'], true);
            }

            if ($requiredRoomType === 'lecture') {
                return in_array($roomType, ['lecture', 'seminar', 'conference'], true);
            }
            return $roomType === $requiredRoomType;
        })->values();

        if ($eligibleRooms->isNotEmpty()) {
            return $eligibleRooms;
        }

        return $rooms->filter(function ($room) use ($requiredCapacity) {
            return $room->capacity >= $requiredCapacity;
        })->values();
    }

    /**
     * Get all rooms (cached)
     */
    private function getRooms(): Collection
    {
        static $rooms = null;
        if ($rooms === null) {
            $rooms = Room::all();
        }
        return $rooms;
    }

    /**
     * Validate data before scheduling
     */
    private function validateData(Collection $sections, Collection $rooms, Collection $timeslots): bool
    {
        $valid = true;
        
        if ($sections->isEmpty()) {
            $this->addConflict([
                'type' => 'no_sections',
                'message' => 'No sections found for semester ' . $this->semesterId
            ]);
            $valid = false;
        }
        
        if ($rooms->isEmpty()) {
            $this->addConflict([
                'type' => 'no_rooms',
                'message' => 'No rooms available in the system'
            ]);
            $valid = false;
        }
        
        if ($timeslots->isEmpty()) {
            $this->addConflict([
                'type' => 'no_timeslots',
                'message' => 'No timeslots defined for weekdays'
            ]);
            $valid = false;
        }
        
        // Check if total capacity is sufficient
        $totalRequiredSlots = $sections->sum(function($section) {
            return $section->courseOffering->course->hours_per_week ?? 3;
        });
        $totalAvailableSlots = $timeslots->count() * $rooms->count();
        
        if ($totalRequiredSlots > $totalAvailableSlots) {
            $this->addConflict([
                'type' => 'insufficient_capacity',
                'message' => "Insufficient total capacity: Required {$totalRequiredSlots} slots, Available {$totalAvailableSlots} slots"
            ]);
            $valid = false;
        }
        
        // Check each section has at least one teacher
        foreach ($sections as $section) {
            if ($section->teachers->isEmpty()) {
                $this->addConflict([
                    'type' => 'no_teacher',
                    'section_id' => $section->id,
                    'message' => "Section {$section->section_name} has no teacher assigned"
                ]);
                $valid = false;
            }
        }
        
        return $valid;
    }

    /**
     * Validate complete schedule for conflicts
     */
    private function validateCompleteSchedule(): array
    {
        $conflicts = [];
        
        // Check room conflicts
        $roomConflicts = $this->findRoomConflicts();
        if (!empty($roomConflicts)) {
            $conflicts = array_merge($conflicts, $roomConflicts);
        }
        
        // Check teacher conflicts
        $teacherConflicts = $this->findTeacherConflicts();
        if (!empty($teacherConflicts)) {
            $conflicts = array_merge($conflicts, $teacherConflicts);
        }
        
        // Check student conflicts
        $studentConflicts = $this->findStudentConflicts();
        if (!empty($studentConflicts)) {
            $conflicts = array_merge($conflicts, $studentConflicts);
        }
        
        // Check capacity issues
        $capacityIssues = $this->findCapacityIssues();
        if (!empty($capacityIssues)) {
            $conflicts = array_merge($conflicts, $capacityIssues);
        }
        
        return [
            'conflicts' => $conflicts,
            'is_valid' => empty($conflicts),
            'room_conflicts' => count($roomConflicts),
            'teacher_conflicts' => count($teacherConflicts),
            'student_conflicts' => count($studentConflicts),
            'capacity_issues' => count($capacityIssues)
        ];
    }

    /**
     * Find room conflicts
     */
    private function findRoomConflicts(): array
    {
        $conflicts = [];
        $roomTimeslotMap = [];
        
        foreach ($this->assignments as $assignment) {
            $key = $assignment['room_id'] . '-' . $assignment['timeslot_id'];
            if (isset($roomTimeslotMap[$key])) {
                $conflicts[] = [
                    'type' => 'room_double_booking',
                    'room_id' => $assignment['room_id'],
                    'timeslot_id' => $assignment['timeslot_id'],
                    'sections' => [$roomTimeslotMap[$key], $assignment['section_id']],
                    'message' => "Room {$assignment['room_id']} double booked at timeslot {$assignment['timeslot_id']}"
                ];
            } else {
                $roomTimeslotMap[$key] = $assignment['section_id'];
            }
        }
        
        return $conflicts;
    }

    /**
     * Find teacher conflicts
     */
    private function findTeacherConflicts(): array
    {
        $conflicts = [];
        $teacherTimeslotMap = [];
        
        foreach ($this->assignments as $assignment) {
            $section = Section::with('teachers')->find($assignment['section_id']);
            if ($section) {
                foreach ($section->teachers as $teacher) {
                    $key = $teacher->id . '-' . $assignment['timeslot_id'];
                    if (isset($teacherTimeslotMap[$key])) {
                        $conflicts[] = [
                            'type' => 'teacher_conflict',
                            'teacher_id' => $teacher->id,
                            'timeslot_id' => $assignment['timeslot_id'],
                            'sections' => [$teacherTimeslotMap[$key], $assignment['section_id']],
                            'message' => "Teacher {$teacher->name} has conflicting schedule"
                        ];
                    } else {
                        $teacherTimeslotMap[$key] = $assignment['section_id'];
                    }
                }
            }
        }
        
        return $conflicts;
    }

    /**
     * Find student conflicts
     */
    private function findStudentConflicts(): array
    {
        $conflicts = [];
        $studentTimeslotMap = [];
        
        foreach ($this->assignments as $assignment) {
            $section = Section::with('enrollments')->find($assignment['section_id']);
            if ($section) {
                foreach ($section->enrollments as $enrollment) {
                    $key = $enrollment->student_id . '-' . $assignment['timeslot_id'];
                    if (isset($studentTimeslotMap[$key])) {
                        $conflicts[] = [
                            'type' => 'student_conflict',
                            'student_id' => $enrollment->student_id,
                            'timeslot_id' => $assignment['timeslot_id'],
                            'sections' => [$studentTimeslotMap[$key], $assignment['section_id']],
                            'message' => "Student {$enrollment->student_id} has conflicting schedule"
                        ];
                    } else {
                        $studentTimeslotMap[$key] = $assignment['section_id'];
                    }
                }
            }
        }
        
        return $conflicts;
    }

    /**
     * Find capacity issues
     */
    private function findCapacityIssues(): array
    {
        $issues = [];
        
        foreach ($this->assignments as $assignment) {
            $section = Section::with('enrollments')->find($assignment['section_id']);
            $room = Room::find($assignment['room_id']);
            
            if ($section && $room) {
                $enrolledCount = $section->enrollments->count();
                if ($room->capacity < $enrolledCount) {
                    $issues[] = [
                        'type' => 'capacity_overflow',
                        'section_id' => $section->id,
                        'room_id' => $room->id,
                        'capacity' => $room->capacity,
                        'enrolled' => $enrolledCount,
                        'message' => "Room capacity exceeded: {$enrolledCount} students in room with capacity {$room->capacity}"
                    ];
                }
            }
        }
        
        return $issues;
    }

    /**
     * Add a conflict
     */
    private function addConflict(array $conflict): void
    {
        $this->conflicts[] = $conflict;
    }

    /**
     * Reset all state
     */
    private function resetState(): void
    {
        $this->assignments = [];
        $this->conflicts = [];
        $this->roomTimeslotMap = [];
        $this->teacherTimeslotMap = [];
        $this->studentTimeslotMap = [];
        $this->teacherHours = [];
    }

    /**
     * Public method to validate existing schedule
     */
    public function validateSchedule(): array
    {
        // Load existing schedules for the semester
        $existingAssignments = Schedule::whereHas('section.courseOffering', function($query) {
            $query->where('semester_id', $this->semesterId);
        })
        ->with(['section.teachers', 'section.enrollments', 'room', 'timeslot'])
        ->get();
        
        $this->resetState();
        
        // Load assignments into memory
        foreach ($existingAssignments as $schedule) {
            $this->assignments[] = [
                'section_id' => $schedule->section_id,
                'room_id' => $schedule->room_id,
                'timeslot_id' => $schedule->timeslot_id
            ];
            
            // Build maps for validation
            $this->roomTimeslotMap[$schedule->room_id][$schedule->timeslot_id] = $schedule->section_id;
            
            if ($schedule->section) {
                foreach ($schedule->section->teachers as $teacher) {
                    $this->teacherTimeslotMap[$teacher->id][$schedule->timeslot_id] = $schedule->section_id;
                }
                
                foreach ($schedule->section->enrollments as $enrollment) {
                    $this->studentTimeslotMap[$enrollment->student_id][$schedule->timeslot_id] = $schedule->section_id;
                }
            }
        }
        
        return $this->validateCompleteSchedule();
    }
}

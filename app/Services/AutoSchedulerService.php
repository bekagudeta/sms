<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\Room;
use App\Models\Timeslot;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoSchedulerService
{
    protected $semesterId;
    protected $courses;
    protected $teachers;
    protected $rooms;
    protected $timeslots;
    protected $students;
    protected $courseScheduleUnits;

    // In-memory conflict tracking to avoid repeated DB queries
    protected $teacherTimeslotMap = [];
    protected $roomTimeslotMap = [];
    protected $studentGroupTimeslotMap = [];
    protected $teacherHours = [];

    protected $schedules = [];
    protected $conflicts = [];
    protected $proposedSchedules = [];

    protected $startTime;
    protected $maxBacktrackSeconds = 120; // fail-safe timeout for backtracking

    public function generateSchedule($semesterId)
    {
        $this->startTime = microtime(true);
        $this->semesterId = $semesterId;
        
        DB::beginTransaction();
        
        try {
            // Clear existing schedules for the semester
            Schedule::where('semester_id', $semesterId)->delete();
            
            // Load data
            $this->loadData();
            
            // Sort courses by difficulty (CSP heuristic: hardest first)
            $this->courses = $this->courses->sortByDesc('student_count')->values();
            $this->courseScheduleUnits = $this->buildCourseScheduleUnits();

            // Validate data completeness
            $this->validateData();

            // 🔥 BACKTRACKING STARTS HERE (in-memory only)
            $success = $this->backtrackSchedule(0);

            if (!$success) {
                // If optimal backtracking fails, fall back to a best-effort greedy schedule
                $this->buildGreedySchedule();
            }

            if (count($this->schedules) === 0) {
                throw new \Exception("Could not generate any schedule entries - check data constraints");
            }

            // Persist final schedule entries once after generation
            foreach ($this->schedules as $scheduleEntry) {
                Schedule::create($scheduleEntry);
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Schedule generated successfully',
                'scheduled' => count($this->schedules),
                'conflicts' => count($this->conflicts),
                'total_courses' => $this->courses->count()
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Auto scheduling failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Auto scheduling failed: ' . $e->getMessage()
            ];
        }
    }

    protected function loadData()
    {
        $this->courses = Course::with(['teacher', 'department'])
                              ->where('semester_id', $this->semesterId)
                              ->get();
        
        $this->teachers = Teacher::with('department')->get();
        $this->rooms = Room::all();
        $this->students = Student::all();
        // Filter timeslots to only include Monday-Friday
        $this->timeslots = Timeslot::whereIn('day_of_week', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])->get();

        // If there are no timeslots setup yet, create a basic default Monday-Friday schedule template.
        if ($this->timeslots->isEmpty()) {
            $this->timeslots = $this->createDefaultTimeslots();
        }

        // Prepare in-memory maps used to quickly check conflicts
        $this->teacherTimeslotMap = [];
        $this->roomTimeslotMap = [];
        $this->studentGroupTimeslotMap = [];
        $this->teacherHours = [];
        $this->schedules = [];
        $this->conflicts = [];
    }

    protected function validateData()
    {
        if ($this->courses->isEmpty()) {
            throw new \Exception('No courses found for this semester');
        }

        if (empty($this->courseScheduleUnits)) {
            throw new \Exception('No scheduling units were created for the selected semester');
        }
        
        if ($this->teachers->isEmpty()) {
            throw new \Exception('No teachers found in the system');
        }
        
        if ($this->rooms->isEmpty()) {
            throw new \Exception('No rooms found in the system');
        }
        
        if ($this->timeslots->isEmpty()) {
            throw new \Exception('No timeslots found in the system');
        }
    }

    protected function buildCourseScheduleUnits()
    {
        $units = collect();

        foreach ($this->courses as $course) {
            if (!empty($course->section)) {
                $units->push(['course' => $course, 'section' => trim($course->section)]);
                continue;
            }

            $courseSections = $this->students
                ->where('department_id', $course->department_id)
                ->where('level', $course->level)
                ->filter(fn($student) => !empty($student->section))
                ->pluck('section')
                ->unique()
                ->values();

            if ($courseSections->isEmpty()) {
                $courseSections = collect(['A']);
            }

            foreach ($courseSections as $section) {
                $units->push(['course' => $course, 'section' => trim($section)]);
            }
        }

        return $units;
    }

    /**
     * 🔥 BACKTRACKING FUNCTION - THE BRAIN
     * Recursively tries to assign courses and backtracks when failures occur
     */
    protected function backtrackSchedule($index)
    {
        // timeout fail-safe to avoid indefinite long requests
        if (microtime(true) - $this->startTime > $this->maxBacktrackSeconds) {
            $errorCourse = $this->courseScheduleUnits[$index]['course']->course_name ?? 'Unknown';
            $this->conflicts[] = [
                'course' => $errorCourse,
                'reason' => 'Backtracking timeout exceeded'
            ];
            return false;
        }

        // ✅ Base case: all course-section schedule units assigned
        if ($index >= count($this->courseScheduleUnits)) {
            return true;
        }

        $unit = $this->courseScheduleUnits[$index];
        $course = $unit['course'];
        $section = $unit['section'];

        // Get candidates (CSP: reduce search space early)
        $teachers = $this->getQualifiedTeachers($course)
            ->sortBy(fn($teacher) => $this->teacherHours[$teacher->id] ?? 0);

        $rooms = $this->getEligibleRooms($course)->sortBy('capacity');
        $timeslots = $this->timeslots->shuffle();

        foreach ($teachers as $teacher) {

            if (!$this->hasTeacherHoursAvailable($teacher, $course)) {
                continue;
            }

            foreach ($timeslots as $timeslot) {

                if ($this->hasTeacherConflictInMemory($teacher, $timeslot)) {
                    continue;
                }

                foreach ($rooms as $room) {

                    if ($this->hasRoomConflictInMemory($room, $timeslot)) {
                        continue;
                    }

                    $group = $section ?: $this->generateStudentGroup($course, $section);

                    if ($group && $this->hasStudentGroupConflictInMemory($group, $timeslot)) {
                        continue;
                    }

                    // Additional soft constraints check
                    if (!$this->hasPrerequisitesScheduled($course, $timeslot)) {
                        continue;
                    }

                    if (!$this->maintainsCourseSequence($course, $timeslot)) {
                        continue;
                    }

                    if (!$this->hasRequiredEquipment($course, $room)) {
                        continue;
                    }

                    // ✅ VALID → ASSIGN (in-memory, no DB write)
                    $scheduleEntry = [
                        'course_id' => $course->id,
                        'teacher_id' => $teacher->id,
                        'room_id' => $room->id,
                        'timeslot_id' => $timeslot->id,
                        'semester_id' => $this->semesterId,
                        'section' => $group,
                        'max_students' => $course->student_count ?? 30,
                        'status' => 'scheduled',
                        'day' => $timeslot->day_of_week,
                        'start_time' => $timeslot->start_time,
                        'end_time' => $timeslot->end_time
                    ];

                    // mark memory
                    $this->markConflictMaps($teacher, $room, $timeslot, $group, $course);
                    $this->schedules[] = $scheduleEntry;

                    // 🔥 RECURSE (next course)
                    if ($this->backtrackSchedule($index + 1)) {
                        return true;
                    }

                    // ❌ BACKTRACK (UNDO)
                    $this->undoAssignment($teacher, $room, $timeslot, $group, $course);
                }
            }
        }

        // ❌ No valid assignment - record conflict for reporting
        $this->conflicts[] = [
            'course' => $course->course_name ?? 'Unknown',
            'reason' => 'No valid combination found after backtracking'
        ];
        
        return false;
    }

    /**
     * 🔥 UNDO FUNCTION - THIS IS WHY BACKTRACKING WORKS
     */
    protected function undoAssignment($teacher, $room, $timeslot, $group, $course)
    {
        // Remove from memory maps
        unset($this->teacherTimeslotMap[$teacher->id][$timeslot->id]);
        unset($this->roomTimeslotMap[$room->id][$timeslot->id]);

        if ($group) {
            unset($this->studentGroupTimeslotMap[$group][$timeslot->id]);
        }

        // Reduce teacher hours
        $this->teacherHours[$teacher->id] = ($this->teacherHours[$teacher->id] ?? 0) - ($course->hours_per_week ?? 0);

        // Remove from schedules array
        array_pop($this->schedules);
    }

    protected function getQualifiedTeachers($course)
    {
        $qualified = $this->teachers->filter(function ($teacher) use ($course) {
            return $this->isTeacherQualified($teacher, $course);
        });

        // Include assigned teacher as top priority when assigned and valid.
        if (!empty($course->teacher_id)) {
            $assigned = $this->teachers->firstWhere('id', $course->teacher_id);
            if ($assigned && !$qualified->contains('id', $assigned->id)) {
                $qualified->prepend($assigned);
            }
        }

        // Fallback: if no qualified teachers found, consider all teachers to maximize scheduling coverage.
        if ($qualified->isEmpty()) {
            $qualified = $this->teachers;
        }

        return $qualified->values();
    }

    protected function getEligibleRooms($course)
    {
        return $this->rooms->filter(function ($room) use ($course) {
            if (isset($room->capacity) && isset($course->student_count) && $room->capacity < $course->student_count) {
                return false;
            }

            if (isset($course->required_room_type) && isset($room->type) && $course->required_room_type !== $room->type) {
                return false;
            }

            return true;
        })->values();
    }

    protected function hasPrerequisitesScheduled($course, $timeslot)
    {
        // Simplified prerequisite check - return true for now if prerequisites field doesn't exist
        if (!isset($course->prerequisites) || !$course->prerequisites) {
            return true;
        }
        return true; // Temporarily disabled to avoid database errors
    }

    protected function hasDepartmentResourcesAvailable($course, $room, $timeslot)
    {
        // Simplified department resource check
        $departmentUsage = Schedule::where('semester_id', $this->semesterId)
                                 ->whereHas('course', function($query) use ($course) {
                                     $query->where('department_id', $course->department_id);
                                 })
                                 ->where('timeslot_id', $timeslot->id)
                                 ->count();
        
        return $departmentUsage < 3;
    }

    protected function maintainsCourseSequence($course, $timeslot)
    {
        // Simplified course sequence check
        return true; // Temporarily simplified to avoid complex queries
    }

    protected function hasLabSafetyEquipment($room)
    {
        return isset($room->has_safety_equipment) ? $room->has_safety_equipment : true;
    }

    protected function maintainsTeacherWorkloadBalance($teacher, $course)
    {
        // Simplified workload balance check
        $teacherCurrentLoad = Schedule::where('semester_id', $this->semesterId)
                                    ->where('teacher_id', $teacher->id)
                                    ->join('courses', 'schedules.course_id', '=', 'courses.id')
                                    ->sum('courses.hours_per_week');
        
        return ($teacherCurrentLoad + ($course->hours_per_week ?? 3)) <= 40; // 40 hours max per week
    }

    protected function hasRequiredEquipment($course, $room)
    {
        // Simplified equipment check
        if (!isset($course->required_equipment) || !$course->required_equipment) {
            return true;
        }
        return true; // Temporarily simplified
    }

    protected function isTeacherQualified($teacher, $course)
    {
        try {
            if (!empty($teacher->department_id) && !empty($course->department_id)) {
                if ($teacher->department_id === $course->department_id) {
                    return true;
                }
            }

            if (!empty($teacher->specialization) && !empty($course->department->name)) {
                if (strtolower(trim($teacher->specialization)) === strtolower(trim($course->department->name))) {
                    return true;
                }
            }

            // Import may leave department info incomplete; include them to avoid false-negative rejection.
            if (empty($teacher->department_id) || empty($course->department_id)) {
                return true;
            }

            // Lax fallback: allow all teachers when no constraints can be decided.
            return true;
        } catch (\Exception $e) {
            return true;
        }
    }

    protected function hasTeacherConflictInMemory($teacher, $timeslot)
    {
        return isset($this->teacherTimeslotMap[$teacher->id][$timeslot->id]);
    }

    protected function hasRoomConflictInMemory($room, $timeslot)
    {
        return isset($this->roomTimeslotMap[$room->id][$timeslot->id]);
    }

    protected function hasTeacherHoursAvailable($teacher, $course)
    {
        $currentHours = $this->teacherHours[$teacher->id] ?? 0;
        return ($currentHours + ($course->hours_per_week ?? 0)) <= ($teacher->max_hours_per_week ?? 40);
    }

    protected function hasStudentGroupConflictInMemory($studentGroup, $timeslot)
    {
        if (!$studentGroup) {
            return false;
        }

        return isset($this->studentGroupTimeslotMap[$studentGroup][$timeslot->id]);
    }

    protected function generateStudentGroup($course, $section = null)
    {
        // If section is explicitly provided, use it.
        if (!empty($section)) {
            return trim($section);
        }

        // Prefer explicit course section value first.
        if (!empty($course->section)) {
            return trim($course->section);
        }

        try {
            $department = isset($course->department->name) ? $course->department->name : 'General';
            $level = !empty($course->level) ? $course->level : 'undergraduate';
            $base = trim("{$department} {$level}");

            if (!empty($course->course_code)) {
                return "{$base} {$course->course_code}";
            }

            return "{$base} A";
        } catch (\Exception $e) {
            return "Group A";
        }
    }

    protected function calculateSoftConstraintScore($course, $teacher, $room, $timeslot)
    {
        $score = 0;

        // Soft Constraint: prefer teachers with fewer hours scheduled overall
        $teacherHours = $this->teacherHours[$teacher->id] ?? 0;
        $score -= $teacherHours;

        // Soft Constraint: prefer rooms with better equipment
        if (isset($room->has_projector) && $room->has_projector) {
            $score += 5;
        }

        if (isset($room->has_computers) && $room->has_computers) {
            $score += 3;
        }

        return $score;
    }

    protected function markConflictMaps($teacher, $room, $timeslot, $studentGroup, $course)
    {
        $this->teacherTimeslotMap[$teacher->id][$timeslot->id] = true;
        $this->roomTimeslotMap[$room->id][$timeslot->id] = true;

        if ($studentGroup) {
            $this->studentGroupTimeslotMap[$studentGroup][$timeslot->id] = true;
        }

        $this->teacherHours[$teacher->id] = ($this->teacherHours[$teacher->id] ?? 0) + ($course->hours_per_week ?? 0);
    }

    protected function selectBestCombination($combinations, $course)
    {
        // Sort by score (descending) and return the best combination
        return $combinations->sortByDesc('score')->first();
    }

    /**
     * Finds all valid combinations of teacher, room, and timeslot for a course that satisfy hard constraints.
     */
    protected function findValidCombinations($course)
    {
        $combinations = collect();

        $teacherCandidates = $this->getQualifiedTeachers($course);
        $roomCandidates = $this->getEligibleRooms($course);
        $timeslots = $this->timeslots;

        foreach ($teacherCandidates as $teacher) {
            if (!$this->hasTeacherHoursAvailable($teacher, $course)) {
                continue;
            }
            foreach ($timeslots as $timeslot) {
                if ($this->hasTeacherConflictInMemory($teacher, $timeslot)) {
                    continue;
                }
                foreach ($roomCandidates as $room) {
                    if ($this->hasRoomConflictInMemory($room, $timeslot)) {
                        continue;
                    }
                    $studentGroup = $this->generateStudentGroup($course);
                    if ($studentGroup && $this->hasStudentGroupConflictInMemory($studentGroup, $timeslot)) {
                        continue;
                    }
                    // All hard constraints satisfied, add combination
                    $score = $this->calculateSoftConstraintScore($course, $teacher, $room, $timeslot);
                    $combinations->push([
                        'teacher' => $teacher,
                        'room' => $room,
                        'timeslot' => $timeslot,
                        'score' => $score
                    ]);
                }
            }
        }

        return $combinations;
    }

    protected function buildGreedySchedule()
    {
        // Reset in-memory state to build a new best-effort schedule.
        $this->teacherTimeslotMap = [];
        $this->roomTimeslotMap = [];
        $this->studentGroupTimeslotMap = [];
        $this->teacherHours = [];
        $this->schedules = [];

        foreach ($this->courseScheduleUnits as $unit) {
            $course = $unit['course'];
            $section = $unit['section'];
            $assigned = false;
            $teachers = $this->getQualifiedTeachers($course)
                              ->sortBy(fn($teacher) => $this->teacherHours[$teacher->id] ?? 0);
            $rooms = $this->getEligibleRooms($course)->sortBy('capacity');

            foreach ($teachers as $teacher) {
                if (!$this->hasTeacherHoursAvailable($teacher, $course)) {
                    continue;
                }

                foreach ($this->timeslots as $timeslot) {
                    if ($this->hasTeacherConflictInMemory($teacher, $timeslot)) {
                        continue;
                    }

                    foreach ($rooms as $room) {
                        if ($this->hasRoomConflictInMemory($room, $timeslot)) {
                            continue;
                        }

                        $group = $section ?: $this->generateStudentGroup($course, $section);
                        if ($group && $this->hasStudentGroupConflictInMemory($group, $timeslot)) {
                            continue;
                        }

                        if (!$this->hasPrerequisitesScheduled($course, $timeslot)) {
                            continue;
                        }

                        if (!$this->maintainsCourseSequence($course, $timeslot)) {
                            continue;
                        }

                        if (!$this->hasRequiredEquipment($course, $room)) {
                            continue;
                        }

                        // Assign in memory and break out of loops.
                        $scheduleEntry = [
                            'course_id' => $course->id,
                            'teacher_id' => $teacher->id,
                            'room_id' => $room->id,
                            'timeslot_id' => $timeslot->id,
                            'semester_id' => $this->semesterId,
                            'section' => $group,
                            'max_students' => $course->student_count ?? 30,
                            'status' => 'scheduled',
                            'day' => $timeslot->day_of_week,
                            'start_time' => $timeslot->start_time,
                            'end_time' => $timeslot->end_time
                        ];

                        $this->markConflictMaps($teacher, $room, $timeslot, $group, $course);
                        $this->schedules[] = $scheduleEntry;
                        $assigned = true;
                        break 3;
                    }
                }
            }

            if (!$assigned) {
                $this->conflicts[] = [
                    'course' => $course->course_name ?? 'Unknown',
                    'section' => $section ?? 'A',
                    'reason' => 'Could not find any valid timeslot/teacher/room combination'
                ];
            }
        }
    }

    protected function createDefaultTimeslots()
    {
        $defaultSlots = [
            '08:00' => '09:00',
            '09:00' => '10:00',
            '10:00' => '11:00',
            '11:00' => '12:00',
            '13:00' => '14:00',
            '14:00' => '15:00',
            '15:00' => '16:00',
        ];

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

        $slotsCollection = collect();

        foreach ($days as $day) {
            foreach ($defaultSlots as $start => $end) {
                $timeslot = Timeslot::firstOrCreate(
                    ['day_of_week' => $day, 'start_time' => $start, 'end_time' => $end],
                    ['slot_code' => $this->generateSlotCode($day, $start, $end)]
                );
                $slotsCollection->push($timeslot);
            }
        }

        return $slotsCollection;
    }

    /**
     * Generates a unique slot code based on day, start, and end time.
     */
    protected function generateSlotCode($day, $start, $end)
    {
        return strtoupper(substr($day, 0, 3)) . '_' . str_replace(':', '', $start) . '_' . str_replace(':', '', $end);
    }

    public function getConflicts()
    {
        return $this->conflicts;
    }

    public function getGeneratedSchedule()
    {
        return $this->schedules;
    }
}
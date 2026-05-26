<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\Timeslot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoSchedulerService
{
    protected $semesterId;

    protected $sections;

    protected $teachers;

    protected $rooms;

    protected $timeslots;

    // In-memory state for backtracking
    protected $teacherTimeslotMap = [];

    protected $roomTimeslotMap = [];

    protected $studentTimeslotMap = [];

    protected $teacherHours = [];

    protected $sectionAssignments = [];

    // Conflict tracking
    protected $conflictCount = 0;

    protected $conflictDetails = [];

    // Day distribution tracking
    protected $dayUsageCount = [];

    protected $teacherDayUsage = [];

    // Timeslots grouped by day
    protected $timeslotsByDay = [];

    // Unscheduled section counter
    protected $unscheduledSections = 0;

    // Precomputed data for performance
    protected $sectionStudentIds = [];

    protected $roomCache = [];

    protected $teacherCourseMap = [];

    protected $availableTimeslotsCache = [];

    // Full section tracking
    protected $totalSectionCount = 0;

    // Best solution tracking
    protected $bestSchedule = null;

    protected $bestScore = -INF;

    protected $currentSchedule = [];

    protected $bestPartialSchedule = [];

    protected $bestPartialScore = -INF;

    // Performance & configuration
    protected $maxAttempts = 3;

    protected $maxBacktrackSteps = 5000;

    protected $backtrackCount = 0;

    protected $timeLimitSeconds = 10;

    protected $startTime;

    protected $maxCombinationsPerSection = 100;

    // Soft constraint weights
    protected $weights = [
        'teacher_load_balance' => 10,
        'room_preference' => 5,
        'timeslot_preference' => 3,
        'student_distribution' => 2,
        'compact_schedule' => 4,
        'day_distribution' => 8, // New weight for spreading across days
    ];

    public function generateSchedule($semesterId)
    {
        $this->startTime = microtime(true);
        $this->semesterId = $semesterId;

        DB::beginTransaction();

        try {
            // Clear existing schedules for this semester only
            Schedule::whereHas('section.courseOffering', function ($q) {
                $q->where('semester_id', $this->semesterId);
            })->delete();

            // Load and prepare data
            $this->loadData();
            $this->validateData();
            $this->precomputeData();

            $requiredSlotsBySection = $this->getRequiredSlotsBySection();

            // Try simple scheduling first. Accept it only if it fully covers the semester.
            Log::info('Attempting simple scheduling engine first');
            $engine = new SchedulingEngine;
            $engineResult = $engine->generateSchedule($this->semesterId);
            $simpleSchedule = $this->filterSemesterSchedule($engineResult['assignments'] ?? []);
            $simpleCoverage = $this->analyzeScheduleCoverage($simpleSchedule, $requiredSlotsBySection);
            $simpleValidationErrors = $simpleCoverage['complete']
                ? $this->validateGeneratedSchedule($simpleSchedule)
                : [];

            $finalSchedule = $simpleSchedule;
            $coverage = $simpleCoverage;
            $engineUsed = 'simple';

            if (! $coverage['complete'] || ! empty($simpleValidationErrors)) {
                Log::info('Simple engine did not produce a complete schedule; attempting advanced backtracking algorithm', [
                    'missing_sections' => count($coverage['missing']),
                    'validation_errors' => count($simpleValidationErrors),
                    'scheduled_slots' => $coverage['scheduled_slots'],
                    'required_slots' => $coverage['required_slots'],
                ]);

                $finalSchedule = $this->attemptAdvancedScheduling();
                $coverage = $this->analyzeScheduleCoverage($finalSchedule, $requiredSlotsBySection);
                $engineUsed = 'advanced';
            }

            if (! $coverage['complete']) {
                $this->conflictDetails = $coverage['missing'];
                throw new \Exception($this->formatIncompleteScheduleMessage($coverage));
            }

            $validationErrors = $this->validateGeneratedSchedule($finalSchedule);
            if (! empty($validationErrors)) {
                $this->conflictDetails = $validationErrors;
                throw new \Exception('Generated schedule has conflicts: '.$validationErrors[0]['message']);
            }

            $finalSchedule = $this->normalizeScheduleEntries($finalSchedule);
            $scheduledSectionIds = collect($finalSchedule)->pluck('section_id')->unique();
            $this->sectionAssignments = $scheduledSectionIds->mapWithKeys(function ($id) {
                return [$id => true];
            })->toArray();
            $this->unscheduledSections = 0;
            $this->conflictCount = 0;
            $this->conflictDetails = [];

            // Persist the complete schedule.
            foreach ($finalSchedule as $scheduleEntry) {
                Schedule::create($scheduleEntry);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Schedule generated successfully using '.$engineUsed.' engine',
                'scheduled' => count($finalSchedule),
                'total_sections' => $this->totalSectionCount,
                'scheduled_sections' => $scheduledSectionIds->count(),
                'required_slots' => $coverage['required_slots'],
                'scheduled_slots' => $coverage['scheduled_slots'],
                'conflicts' => 0,
                'day_distribution' => $this->getDayDistributionStats(),
                'partial_schedule' => false,
                'engine' => $engineUsed,
                'execution_time' => round(microtime(true) - $this->startTime, 2),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Schedule generation failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Schedule generation failed: '.$e->getMessage(),
                'execution_time' => round(microtime(true) - $this->startTime, 2),
            ];
        }
    }

    protected function getRequiredSlotsBySection()
    {
        return $this->sections->mapWithKeys(function ($section) {
            return [
                $section->id => max(1, (int) ($section->courseOffering->course->hours_per_week ?? 3)),
            ];
        });
    }

    protected function filterSemesterSchedule(array $assignments): array
    {
        $semesterSectionIds = $this->sections->pluck('id')->flip();

        return collect($assignments)
            ->filter(function ($assignment) use ($semesterSectionIds) {
                return isset($assignment['section_id'], $assignment['room_id'], $assignment['timeslot_id'])
                    && $semesterSectionIds->has($assignment['section_id']);
            })
            ->map(function ($assignment) {
                return [
                    'section_id' => (int) $assignment['section_id'],
                    'room_id' => (int) $assignment['room_id'],
                    'timeslot_id' => (int) $assignment['timeslot_id'],
                ];
            })
            ->unique(fn ($assignment) => $assignment['section_id'].'-'.$assignment['timeslot_id'])
            ->values()
            ->all();
    }

    protected function normalizeScheduleEntries(array $assignments): array
    {
        return collect($assignments)
            ->map(function ($assignment) {
                return [
                    'section_id' => (int) $assignment['section_id'],
                    'room_id' => (int) $assignment['room_id'],
                    'timeslot_id' => (int) $assignment['timeslot_id'],
                ];
            })
            ->values()
            ->all();
    }

    protected function analyzeScheduleCoverage(array $assignments, $requiredSlotsBySection): array
    {
        $slotCounts = collect($assignments)->countBy('section_id');
        $missing = [];

        foreach ($requiredSlotsBySection as $sectionId => $requiredSlots) {
            $scheduledSlots = (int) ($slotCounts[$sectionId] ?? 0);

            if ($scheduledSlots < $requiredSlots) {
                $section = $this->sections->firstWhere('id', $sectionId);
                $missing[] = [
                    'type' => 'insufficient_section_slots',
                    'section_id' => $sectionId,
                    'section_name' => $section?->section_name,
                    'course' => $section?->courseOffering?->course?->course_name,
                    'required_slots' => $requiredSlots,
                    'scheduled_slots' => $scheduledSlots,
                    'message' => "Section {$section?->section_name} needs {$requiredSlots} weekly slot(s), but only {$scheduledSlots} were scheduled.",
                ];
            }
        }

        return [
            'complete' => empty($missing),
            'missing' => $missing,
            'required_slots' => (int) collect($requiredSlotsBySection)->sum(),
            'scheduled_slots' => count($assignments),
        ];
    }

    protected function validateGeneratedSchedule(array $assignments): array
    {
        $errors = [];
        $roomTimeslots = [];
        $sectionTimeslots = [];
        $teacherTimeslots = [];
        $teacherLoads = [];
        $teacherModels = [];
        $studentTimeslots = [];

        $sections = $this->sections->keyBy('id');
        $rooms = $this->rooms->keyBy('id');

        foreach ($assignments as $assignment) {
            $section = $sections->get($assignment['section_id']);
            $room = $rooms->get($assignment['room_id']);
            $timeslotId = (int) $assignment['timeslot_id'];

            if (! $section || ! $room) {
                $errors[] = [
                    'type' => 'invalid_assignment_reference',
                    'message' => 'Generated schedule references a missing section or room.',
                ];

                continue;
            }

            $roomKey = $room->id.'-'.$timeslotId;
            if (isset($roomTimeslots[$roomKey])) {
                $errors[] = [
                    'type' => 'room_double_booking',
                    'section_id' => $section->id,
                    'room_id' => $room->id,
                    'timeslot_id' => $timeslotId,
                    'message' => "Room {$room->room_code} is double booked.",
                ];
            }
            $roomTimeslots[$roomKey] = true;

            $sectionKey = $section->id.'-'.$timeslotId;
            if (isset($sectionTimeslots[$sectionKey])) {
                $errors[] = [
                    'type' => 'section_duplicate_timeslot',
                    'section_id' => $section->id,
                    'timeslot_id' => $timeslotId,
                    'message' => "Section {$section->section_name} is scheduled more than once in the same timeslot.",
                ];
            }
            $sectionTimeslots[$sectionKey] = true;

            if ($room->capacity < $section->capacity) {
                $errors[] = [
                    'type' => 'room_capacity',
                    'section_id' => $section->id,
                    'room_id' => $room->id,
                    'message' => "Room {$room->room_code} capacity is too small for section {$section->section_name}.",
                ];
            }

            if (! $this->isRoomSuitableForCourse($room, $section->courseOffering->course)) {
                $errors[] = [
                    'type' => 'room_type',
                    'section_id' => $section->id,
                    'room_id' => $room->id,
                    'message' => "Room {$room->room_code} is not suitable for {$section->courseOffering->course->course_name}.",
                ];
            }

            foreach ($section->teachers as $teacher) {
                $teacherKey = $teacher->id.'-'.$timeslotId;
                if (isset($teacherTimeslots[$teacherKey])) {
                    $errors[] = [
                        'type' => 'teacher_conflict',
                        'section_id' => $section->id,
                        'teacher_id' => $teacher->id,
                        'timeslot_id' => $timeslotId,
                        'message' => "Teacher {$teacher->full_name} has a timeslot conflict.",
                    ];
                }
                $teacherTimeslots[$teacherKey] = true;
                $teacherLoads[$teacher->id] = ($teacherLoads[$teacher->id] ?? 0) + 1;
                $teacherModels[$teacher->id] = $teacher;
            }

            foreach ($this->sectionStudentIds[$section->id] ?? [] as $studentId) {
                $studentKey = $studentId.'-'.$timeslotId;
                if (isset($studentTimeslots[$studentKey])) {
                    $errors[] = [
                        'type' => 'student_conflict',
                        'section_id' => $section->id,
                        'student_id' => $studentId,
                        'timeslot_id' => $timeslotId,
                        'message' => "Student {$studentId} has a timeslot conflict.",
                    ];
                }
                $studentTimeslots[$studentKey] = true;
            }
        }

        foreach ($teacherLoads as $teacherId => $load) {
            $teacher = $teacherModels[$teacherId];
            $maxHours = $teacher->max_hours_per_week ?? 39;

            if ($load > $maxHours) {
                $errors[] = [
                    'type' => 'teacher_workload',
                    'teacher_id' => $teacherId,
                    'message' => "Teacher {$teacher->full_name} is assigned {$load} weekly slot(s), above the {$maxHours} limit.",
                ];
            }
        }

        return $errors;
    }

    protected function formatIncompleteScheduleMessage(array $coverage): string
    {
        $missingCount = count($coverage['missing']);

        return "Unable to generate a complete semester schedule. {$coverage['scheduled_slots']} of {$coverage['required_slots']} required weekly slots were scheduled; {$missingCount} section(s) are incomplete.";
    }

    /**
     * Attempt advanced scheduling using backtracking algorithm
     */
    protected function attemptAdvancedScheduling()
    {
        try {
            // Sort sections by difficulty for better backtracking performance
            $sortedSections = $this->sortSectionsByDifficulty();
            $this->sections = $sortedSections;

            // Get available timeslots
            $availableTimeslots = $this->timeslots;

            // Reset state for clean scheduling attempt
            $this->resetSchedulingState();

            // Attempt backtracking scheduling
            $success = $this->backtrackAssignSections(0, $availableTimeslots);

            if ($success) {
                // Convert current schedule to final format
                $finalSchedule = [];
                foreach ($this->currentSchedule as $assignment) {
                    $finalSchedule[] = $assignment;
                }

                return $finalSchedule;
            }

            // If backtracking fails, try greedy approach as last resort
            return $this->attemptGreedyScheduling();

        } catch (\Exception $e) {
            Log::error('Advanced scheduling failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Greedy scheduling as fallback when backtracking fails
     */
    protected function attemptGreedyScheduling()
    {
        $finalSchedule = [];
        $usedRoomTimeslots = [];
        $usedTeacherTimeslots = [];

        foreach ($this->sections as $section) {
            $course = $section->courseOffering->course;
            $requiredSlots = $course->hours_per_week ?? 3;
            $assignedSlots = 0;

            foreach ($this->timeslots as $timeslot) {
                if ($assignedSlots >= $requiredSlots) {
                    break;
                }

                // Check teacher availability
                $teacherAvailable = true;
                foreach ($section->teachers as $teacher) {
                    $key = $teacher->id.'_'.$timeslot->id;
                    if (isset($usedTeacherTimeslots[$key])) {
                        $teacherAvailable = false;
                        break;
                    }
                }

                if (! $teacherAvailable) {
                    continue;
                }

                // Find available room
                $room = null;
                foreach ($this->rooms as $potentialRoom) {
                    if ($potentialRoom->capacity >= $section->capacity) {
                        $roomKey = $potentialRoom->id.'_'.$timeslot->id;
                        if (! isset($usedRoomTimeslots[$roomKey])) {
                            if ($this->isRoomSuitableForCourse($potentialRoom, $course)) {
                                $room = $potentialRoom;
                                break;
                            }
                        }
                    }
                }

                if ($room) {
                    // Assign schedule
                    $finalSchedule[] = [
                        'section_id' => $section->id,
                        'room_id' => $room->id,
                        'timeslot_id' => $timeslot->id,
                    ];

                    // Mark as used
                    $roomKey = $room->id.'_'.$timeslot->id;
                    $usedRoomTimeslots[$roomKey] = true;

                    foreach ($section->teachers as $teacher) {
                        $teacherKey = $teacher->id.'_'.$timeslot->id;
                        $usedTeacherTimeslots[$teacherKey] = true;
                    }

                    $assignedSlots++;
                }
            }
        }

        return $finalSchedule;
    }

    /**
     * Precompute data for performance optimization
     */
    protected function precomputeData()
    {
        // Precompute student IDs per section for faster conflict checking
        foreach ($this->sections as $section) {
            $this->sectionStudentIds[$section->id] = $section->enrollments->pluck('student_id')->toArray();
        }

        // Group timeslots by day for easier distribution
        $this->timeslotsByDay = $this->timeslots->groupBy('day_of_week');

        // Precompute teacher qualifications
        foreach ($this->teachers as $teacher) {
            $this->teacherCourseMap[$teacher->id] = [];
        }

        // Precompute room availability cache
        foreach ($this->rooms as $room) {
            $this->roomCache[$room->id] = [];
        }
    }

    /**
     * Enhanced section sorting with multiple factors and pruning
     */
    protected function sortSectionsByDifficulty()
    {
        return $this->sections->sortByDesc(function ($section) {
            $difficulty = 0;

            // Factor 1: Enrollment count (primary)
            $difficulty += count($this->sectionStudentIds[$section->id] ?? []) * 10;

            // Factor 2: Teacher scarcity
            $teacherCount = $section->teachers->count();
            $difficulty += (10 - min(10, $teacherCount)) * 5;

            // Factor 3: Course complexity (hours needed)
            $course = $section->courseOffering->course;
            $requiredSlots = $course->hours_per_week ?? 3;
            $difficulty += $requiredSlots * 2;

            // Factor 4: Room constraints
            $eligibleRooms = $this->getEligibleRooms($course)->count();
            if ($eligibleRooms < 3) {
                $difficulty += 20;
            } elseif ($eligibleRooms === 0) {
                $difficulty += 100; // Impossible to schedule
            }

            return $difficulty;
        })->values();
    }

    /**
     * TRUE BACKTRACKING with pruning and forward checking
     */
    protected function backtrackAssignSections($sectionIndex, $availableTimeslots)
    {
        $this->backtrackCount++;

        // Performance limits
        if ($this->backtrackCount > $this->maxBacktrackSteps) {
            return false;
        }

        if (microtime(true) - $this->startTime > $this->timeLimitSeconds) {
            return false;
        }

        // All sections assigned successfully
        if ($sectionIndex >= $this->sections->count()) {
            return true;
        }

        $section = $this->sections[$sectionIndex];
        $course = $section->courseOffering->course;
        $requiredSlots = $course->hours_per_week ?? 3;

        // Get all possible timeslot-room combinations for this section
        $possibleAssignments = $this->getPossibleAssignments($section, $course, $availableTimeslots, $requiredSlots);

        if ($possibleAssignments->isEmpty()) {
            return false; // No possible assignments for this section
        }

        // Limit combinations to prevent explosion
        if ($possibleAssignments->count() > $this->maxCombinationsPerSection) {
            $possibleAssignments = $possibleAssignments->take($this->maxCombinationsPerSection);
        }

        // Sort assignments by score (try best first)
        $possibleAssignments = $possibleAssignments->sortByDesc(function ($assignment) {
            return $assignment['score'];
        });

        // Try each possible assignment
        foreach ($possibleAssignments as $assignment) {
            // Apply assignment
            $this->applyAssignment($section, $assignment);

            // Check if we still need more slots for this section
            $currentSlots = $this->getCurrentSectionSlotCount($section->id);

            if ($currentSlots < $requiredSlots) {
                // Need more slots for this same section
                $result = $this->backtrackAssignSections($sectionIndex, $availableTimeslots);
            } else {
                // Section is complete, move to next section
                $result = $this->backtrackAssignSections($sectionIndex + 1, $availableTimeslots);
            }

            if ($result) {
                return true; // Found valid solution
            }

            // BACKTRACK: Undo assignment
            $this->undoAssignment($section, $assignment);
        }

        return false; // No valid assignment found
    }

    /**
     * Pruning: Check if enough capacity remains for remaining sections
     */
    protected function hasEnoughRemainingCapacity($currentIndex, $currentRequiredSlots)
    {
        $remainingSections = $this->sections->slice($currentIndex);
        $totalRequiredSlots = $remainingSections->sum(function ($section) {
            return $section->courseOffering->course->hours_per_week ?? 3;
        });

        $totalCapacity = $this->timeslots->count() * $this->rooms->count();
        $usedCapacity = count($this->currentSchedule);

        if (($totalCapacity - $usedCapacity) < $totalRequiredSlots) {
            return false;
        }

        // Teacher capacity check
        $teacherLoad = [];
        foreach ($remainingSections as $section) {
            foreach ($section->teachers as $teacher) {
                $teacherLoad[$teacher->id] = ($this->teacherHours[$teacher->id] ?? 0) + ($section->courseOffering->course->hours_per_week ?? 3);
                $maxHours = $teacher->max_hours_per_week ?? 39;
                if ($teacherLoad[$teacher->id] > $maxHours) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Forward checking: Test if assignment leads to impossible state
     */
    protected function forwardCheck($sectionIndex, $assignment)
    {
        $remainingSections = $this->sections->slice($sectionIndex + 1);

        foreach ($remainingSections as $remainingSection) {
            $course = $remainingSection->courseOffering->course;
            $requiredSlots = $course->hours_per_week ?? 3;

            // Get valid timeslots across all days
            $validTimeslots = $this->timeslots->filter(function ($timeslot) use ($remainingSection, $course) {
                return $this->isTimeslotValid($remainingSection, $course, $timeslot)
                    && $this->findAvailableRoom($remainingSection, $course, $timeslot) !== null;
            });

            if ($validTimeslots->count() < $requiredSlots) {
                return false; // Not enough valid slots
            }

            // Check if we can find at least one valid combination across ANY days
            $combinations = $this->getValidCombinationsByDay($validTimeslots, $requiredSlots);
            if ($combinations->isEmpty()) {
                return false;
            }

            $found = false;
            foreach ($combinations as $combination) {
                foreach ($this->getEligibleRooms($course) as $room) {
                    if ($room->capacity < $remainingSection->capacity) {
                        continue;
                    }

                    $roomOK = true;
                    foreach ($combination as $timeslot) {
                        if ($this->hasRoomConflictInMemory($room, $timeslot)) {
                            $roomOK = false;
                            break;
                        }
                    }

                    if ($roomOK) {
                        $found = true;
                        break 2;
                    }
                }
            }

            if (! $found) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get valid combinations grouped by day
     */
    protected function getValidCombinationsByDay($validTimeslots, $requiredSlots)
    {
        if ($validTimeslots->count() < $requiredSlots) {
            return collect();
        }

        return $this->generateTimeslotCombinations($validTimeslots, $requiredSlots);
    }

    protected function generateTimeslotCombinations($timeslots, $requiredSlots, $limit = 1200)
    {
        $result = collect();
        $timeslotArray = $timeslots->values()->all();
        $this->combineTimeslots($timeslotArray, $requiredSlots, 0, [], $result, $limit);

        return $result;
    }

    protected function combineTimeslots(array $timeslotArray, int $requiredSlots, int $start, array $current, &$result, int $limit)
    {
        if ($result->count() >= $limit) {
            return;
        }

        if (count($current) === $requiredSlots) {
            $result->push(collect($current));

            return;
        }

        for ($i = $start; $i < count($timeslotArray); $i++) {
            $current[] = $timeslotArray[$i];
            $this->combineTimeslots($timeslotArray, $requiredSlots, $i + 1, $current, $result, $limit);
            array_pop($current);

            if ($result->count() >= $limit) {
                break;
            }
        }
    }

    /**
     * Get all possible timeslot-room combinations with scoring
     */
    protected function getPossibleAssignments($section, $course, $availableTimeslots, $requiredSlots)
    {
        $possibilities = collect();

        // Find valid timeslots across all weekdays
        $validTimeslots = $availableTimeslots->filter(function ($timeslot) use ($section, $course) {
            return $this->isTimeslotValid($section, $course, $timeslot);
        });

        if ($validTimeslots->isEmpty()) {
            return $possibilities;
        }

        // For simplicity and reliability, assign individual slots rather than combinations
        // This approach is more robust and avoids combination explosion
        foreach ($validTimeslots as $timeslot) {
            $eligibleRooms = $this->getEligibleRooms($course);

            foreach ($eligibleRooms as $room) {
                if ($room->capacity < $section->capacity) {
                    continue;
                }

                if ($this->hasRoomConflictInMemory($room, $timeslot)) {
                    continue;
                }

                $day = $timeslot->day_of_week;
                $score = $this->calculateAssignmentScore($section, $course, [$timeslot], $room);

                $possibilities->push([
                    'timeslots' => collect([$timeslot]),
                    'room' => $room,
                    'day' => $day,
                    'score' => $score,
                ]);

                // Use first available room for this timeslot to avoid duplicates
                break;
            }
        }

        return $possibilities;
    }

    /**
     * Calculate comprehensive score for an assignment
     */
    protected function calculateAssignmentScore($section, $course, $timeslots, $room)
    {
        $score = 100; // Base score

        // Day distribution score - prefer less used days
        $day = $timeslots[0]->day_of_week;
        $currentDayUsage = $this->dayUsageCount[$day] ?? 0;
        $score -= $currentDayUsage * 5; // Penalize overused days

        // Teacher load balance
        $primaryTeacher = $section->teachers->first();
        if ($primaryTeacher) {
            $currentHours = $this->teacherHours[$primaryTeacher->id] ?? 0;
            $score += max(0, (39 - $currentHours)) * 10;

            // Check teacher's day distribution
            $teacherDayUsage = $this->teacherDayUsage[$primaryTeacher->id][$day] ?? 0;
            $score -= $teacherDayUsage * 3; // Penalize teachers teaching too many classes on same day
        }

        // Timeslot preference (avoid early morning/late evening)
        foreach ($timeslots as $timeslot) {
            $hour = (int) substr($timeslot->start_time, 0, 2);
            if ($hour >= 9 && $hour <= 16) {
                $score += 10;
            } elseif ($hour < 8 || $hour > 18) {
                $score -= 5; // Penalty for very early/late
            }
        }

        // Room suitability
        $preferredRoomType = $this->getRequiredRoomType($course);
        if ($preferredRoomType === 'any' || $this->normalizeRoomType($room->type ?? 'lecture') === $preferredRoomType) {
            $score += 20;
        }

        // Student distribution - prefer spreading across days
        $score += $this->weights['day_distribution'];

        return $score;
    }

    /**
     * Apply assignment to in-memory state
     */
    protected function applyAssignment($section, &$assignment)
    {
        $timeslots = $assignment['timeslots'];
        $room = $assignment['room'];
        $day = $assignment['day'];

        // Update day usage
        $this->dayUsageCount[$day] = ($this->dayUsageCount[$day] ?? 0) + count($timeslots);

        foreach ($timeslots as $timeslot) {
            $this->currentSchedule[] = [
                'section_id' => $section->id,
                'room_id' => $room->id,
                'timeslot_id' => $timeslot->id,
            ];

            // Update teacher maps
            foreach ($section->teachers as $teacher) {
                $this->teacherTimeslotMap[$teacher->id][$timeslot->id] = true;
                $this->teacherHours[$teacher->id] = ($this->teacherHours[$teacher->id] ?? 0) + 1;
                $this->teacherDayUsage[$teacher->id][$day] = ($this->teacherDayUsage[$teacher->id][$day] ?? 0) + 1;
            }

            // Update room maps
            $this->roomTimeslotMap[$room->id][$timeslot->id] = true;

            // Update student maps
            foreach ($this->sectionStudentIds[$section->id] ?? [] as $studentId) {
                $this->studentTimeslotMap[$studentId][$timeslot->id] = true;
            }
        }

        $this->sectionAssignments[$section->id] = $assignment;
    }

    /**
     * Undo assignment (BACKTRACKING)
     */
    protected function undoAssignment($section, $assignment)
    {
        $timeslots = $assignment['timeslots'];
        $room = $assignment['room'];
        $day = $assignment['day'];

        // Update day usage
        $this->dayUsageCount[$day] = max(0, ($this->dayUsageCount[$day] ?? 0) - count($timeslots));

        foreach ($timeslots as $timeslot) {
            array_pop($this->currentSchedule);

            // Remove from teacher maps
            foreach ($section->teachers as $teacher) {
                unset($this->teacherTimeslotMap[$teacher->id][$timeslot->id]);
                $this->teacherHours[$teacher->id] = max(0, ($this->teacherHours[$teacher->id] ?? 0) - 1);
                if (($this->teacherHours[$teacher->id] ?? 0) == 0) {
                    unset($this->teacherHours[$teacher->id]);
                }
                $this->teacherDayUsage[$teacher->id][$day] = max(0, ($this->teacherDayUsage[$teacher->id][$day] ?? 0) - 1);
                if (($this->teacherDayUsage[$teacher->id][$day] ?? 0) == 0) {
                    unset($this->teacherDayUsage[$teacher->id][$day]);
                }
            }

            // Remove from room maps
            unset($this->roomTimeslotMap[$room->id][$timeslot->id]);

            // Remove from student maps
            foreach ($this->sectionStudentIds[$section->id] ?? [] as $studentId) {
                unset($this->studentTimeslotMap[$studentId][$timeslot->id]);
            }
        }

        unset($this->sectionAssignments[$section->id]);
    }

    /**
     * Get current slot count for a section
     */
    protected function getCurrentSectionSlotCount($sectionId)
    {
        $count = 0;
        foreach ($this->currentSchedule as $assignment) {
            if ($assignment['section_id'] == $sectionId) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Find available room
     */
    protected function findAvailableRoom($section, $course, $timeslot)
    {
        $eligibleRooms = $this->getEligibleRooms($course);

        // Sort rooms by usage count (least used first)
        $eligibleRooms = $eligibleRooms->sortBy(function ($room) {
            return count($this->roomTimeslotMap[$room->id] ?? []);
        });

        foreach ($eligibleRooms as $room) {
            if ($room->capacity < $section->capacity) {
                continue;
            }
            if (! $this->isRoomSuitableForCourse($room, $course)) {
                continue;
            }
            if ($this->hasRoomConflictInMemory($room, $timeslot)) {
                continue;
            }

            return $room;
        }

        return null;
    }

    /**
     * Find available room with caching
     */
    protected function findAvailableRoomWithCache($section, $course, $timeslot)
    {
        return $this->findAvailableRoom($section, $course, $timeslot);
    }

    /**
     * Get timeslot usage count for load balancing
     */
    protected function getTimeslotUsageCount($timeslot)
    {
        $count = 0;
        foreach ($this->roomTimeslotMap as $roomTimeslots) {
            if (isset($roomTimeslots[$timeslot->id])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get current solution score
     */
    protected function getCurrentScore()
    {
        $score = 0;

        // Teacher load balance score
        foreach ($this->teacherHours as $hours) {
            $score += max(0, 39 - $hours) * 10;
        }

        // Room utilization score (reward efficient usage)
        foreach ($this->roomTimeslotMap as $roomTimeslots) {
            $score += count($roomTimeslots) * 5;
        }

        // Day distribution score - reward even distribution across days
        $avgUsage = array_sum($this->dayUsageCount) / max(1, count($this->dayUsageCount));
        foreach ($this->dayUsageCount as $day => $usage) {
            $deviation = abs($usage - $avgUsage);
            $score -= $deviation * 2; // Penalize uneven distribution
        }

        // Penalize unscheduled sections
        $scheduledSections = count($this->sectionAssignments);
        $totalSections = $this->sections->count();
        $score -= ($totalSections - $scheduledSections) * 100;

        return $score;
    }

    /**
     * Get day distribution statistics
     */
    protected function getDayDistributionStats()
    {
        $stats = [];
        foreach ($this->dayUsageCount as $day => $count) {
            $stats[$day] = $count;
        }

        return $stats;
    }

    /**
     * Prepare final result
     */
    protected function prepareResult($success)
    {
        $executionTime = microtime(true) - $this->startTime;
        $totalRequired = $this->sections->sum(function ($s) {
            return $s->courseOffering->course->hours_per_week ?? 3;
        });

        $scheduledCount = count($this->bestSchedule ?? $this->currentSchedule);
        $totalSections = $this->totalSectionCount;
        $scheduledSectionCount = count($this->sectionAssignments);
        $conflicts = max(0, $totalSections - $scheduledSectionCount);

        return [
            'success' => ($scheduledCount > 0),
            'perfect' => ($conflicts === 0),
            'message' => ($conflicts === 0 ? 'Schedule generated successfully' : 'Partial schedule generated with conflicts'),
            'scheduled' => $scheduledCount,
            'total_sections' => $totalSections,
            'scheduled_sections' => $scheduledSectionCount,
            'conflicts' => $conflicts,
            'day_distribution' => $this->getDayDistributionStats(),
            'total_required' => $totalRequired,
            'completion_rate' => round(($scheduledCount / max(1, $totalRequired)) * 100, 2),
            'backtrack_steps' => $this->backtrackCount,
            'execution_time' => round($executionTime, 2),
            'best_score' => $this->bestScore,
            'attempts' => $this->maxAttempts,
        ];
    }

    public function getConflicts()
    {
        return $this->conflictDetails ?? [];
    }

    // ========== HELPER METHODS ==========

    protected function loadData()
    {
        $this->sections = Section::with(['courseOffering.course', 'courseOffering.semester', 'teachers', 'enrollments'])
            ->whereHas('courseOffering', function ($q) {
                $q->where('semester_id', $this->semesterId);
            })
            ->get()
            ->filter(function ($section) {
                return $section->courseOffering && $section->courseOffering->course;
            })
            ->values();

        $this->totalSectionCount = $this->sections->count();

        $this->teachers = Teacher::all();
        $this->rooms = Room::all();
        $this->timeslots = Timeslot::whereIn('day_of_week', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        if ($this->timeslots->isEmpty()) {
            $this->timeslots = $this->createDefaultTimeslots();
        }

        $this->timeslotsByDay = $this->timeslots->groupBy('day_of_week');

        $this->resetSchedulingState();

        // --- FEASIBILITY PRE-CHECKS ---
        $totalRequiredSlots = $this->sections->sum(function ($section) {
            return $section->courseOffering->course->hours_per_week ?? 3;
        });
        $totalCapacity = $this->timeslots->count() * $this->rooms->count();

        if ($totalRequiredSlots > $totalCapacity) {
            throw new \Exception("Insufficient total capacity for all required slots. Required: {$totalRequiredSlots}, available: {$totalCapacity}.");
        }

        // Check each section has at least one teacher
        foreach ($this->sections as $section) {
            if ($section->teachers->count() === 0) {
                throw new \Exception('Cannot schedule section '.$section->section_name.' because it has no teacher assigned.');
            }
        }
    }

    protected function validateData()
    {
        if ($this->sections->isEmpty()) {
            throw new \Exception('No sections found for this semester');
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

        foreach ($this->sections as $section) {
            if ($section->teachers->isEmpty()) {
                throw new \Exception('Cannot schedule section '.$section->section_name.' because it has no teacher assigned.');
            }

            $hasEligibleRoom = $this->getEligibleRooms($section->courseOffering->course)
                ->contains(function ($room) use ($section) {
                    return $room->capacity >= $section->capacity;
                });

            if (! $hasEligibleRoom) {
                throw new \Exception('Cannot schedule section '.$section->section_name.' because no eligible room with enough capacity exists for '.$section->courseOffering->course->course_name.'.');
            }
        }
    }

    protected function getEligibleRooms($course)
    {
        return $this->rooms->filter(function ($room) use ($course) {
            return $this->isRoomSuitableForCourse($room, $course);
        })->values();
    }

    protected function hasAnyTeacherConflict($section, $timeslot)
    {
        foreach ($section->teachers as $teacher) {
            if (isset($this->teacherTimeslotMap[$teacher->id][$timeslot->id])) {
                return true;
            }
            if (! $this->hasTeacherHoursAvailable($teacher, $section->courseOffering->course, 1)) {
                return true;
            }
        }

        return false;
    }

    protected function hasAnyStudentConflict($section, $timeslot)
    {
        foreach ($this->sectionStudentIds[$section->id] ?? [] as $studentId) {
            if (isset($this->studentTimeslotMap[$studentId][$timeslot->id])) {
                return true;
            }
        }

        return false;
    }

    protected function hasTeacherHoursAvailable($teacher, $course, $slotsNeeded = 1)
    {
        $currentHours = $this->teacherHours[$teacher->id] ?? 0;
        $maxHours = $teacher->max_hours_per_week ?? 39;

        return ($currentHours + $slotsNeeded) <= $maxHours;
    }

    protected function hasRoomConflictInMemory($room, $timeslot)
    {
        return isset($this->roomTimeslotMap[$room->id][$timeslot->id]);
    }

    protected function getRequiredRoomType($course)
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

    protected function normalizeRoomType($roomType)
    {
        return match (strtolower(trim((string) $roomType))) {
            'laboratory', 'computer lab', 'computer-lab', 'computer_lab' => 'lab',
            'classroom', 'hall', 'auditorium' => 'lecture',
            default => strtolower(trim((string) $roomType)),
        };
    }

    protected function isRoomSuitableForCourse($room, $course)
    {
        $roomType = $this->normalizeRoomType($room->type ?? 'lecture');
        $requiredRoomType = $this->getRequiredRoomType($course);

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
    }

    protected function isTimeslotValid($section, $course, $timeslot)
    {
        // Check teacher conflicts
        foreach ($section->teachers as $teacher) {
            if (isset($this->teacherTimeslotMap[$teacher->id][$timeslot->id])) {
                return false;
            }

            // Check teacher hours limit
            $currentHours = $this->teacherHours[$teacher->id] ?? 0;
            $maxHours = $teacher->max_hours_per_week ?? 39;
            if ($currentHours >= $maxHours) {
                return false;
            }
        }

        // Check student conflicts
        foreach ($this->sectionStudentIds[$section->id] ?? [] as $studentId) {
            if (isset($this->studentTimeslotMap[$studentId][$timeslot->id])) {
                return false;
            }
        }

        return true;
    }

    protected function resetSchedulingState()
    {
        $this->teacherTimeslotMap = [];
        $this->roomTimeslotMap = [];
        $this->studentTimeslotMap = [];
        $this->teacherHours = [];
        $this->sectionAssignments = [];
        $this->currentSchedule = [];
        $this->dayUsageCount = [];
        $this->teacherDayUsage = [];
        $this->roomCache = [];
        $this->unscheduledSections = 0;
        $this->conflictCount = 0;
        $this->conflictDetails = [];
    }

    protected function createDefaultTimeslots()
    {
        $defaultTimeslots = collect();
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $times = [
            ['start' => '08:00', 'end' => '09:30'],
            ['start' => '09:30', 'end' => '11:00'],
            ['start' => '11:00', 'end' => '12:30'],
            ['start' => '12:30', 'end' => '14:00'],
            ['start' => '14:00', 'end' => '15:30'],
            ['start' => '15:30', 'end' => '17:00'],
        ];

        $counter = 1;
        foreach ($days as $day) {
            foreach ($times as $time) {
                $defaultTimeslots->push((object) [
                    'id' => $counter++,
                    'day_of_week' => $day,
                    'start_time' => $time['start'],
                    'end_time' => $time['end'],
                ]);
            }
        }

        return $defaultTimeslots;
    }
}

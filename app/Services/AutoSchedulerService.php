<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Section;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\Room;
use App\Models\Timeslot;
use App\Services\SchedulingEngine;
use Illuminate\Support\Collection;
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
            
            // Try simple scheduling first (fallback)
            Log::info('Attempting simple scheduling engine first');
            $engine = new SchedulingEngine();
            $engineResult = $engine->generateSchedule($this->semesterId);

            $finalSchedule = [];
            if (!empty($engineResult['assignments'])) {
                foreach ($engineResult['assignments'] as $assignment) {
                    $section = Section::with('courseOffering')->find($assignment['section_id']);
                    if ($section && $section->courseOffering->semester_id == $this->semesterId) {
                        $finalSchedule[] = $assignment;
                    }
                }
            }

            if (empty($finalSchedule)) {
                throw new \Exception("Simple scheduling engine failed to generate any schedules");
            }

            // Set scheduled section assignments from final schedule before saving
            $scheduledSectionIds = collect($finalSchedule)->pluck('section_id')->unique();
            $this->sectionAssignments = $scheduledSectionIds->mapWithKeys(function($id) {
                return [$id => true];
            })->toArray();
            $this->unscheduledSections = $this->sections->count() - $scheduledSectionIds->count();
            $this->conflictCount = $this->unscheduledSections;
            $this->conflictDetails = $this->sections->pluck('id')->diff($scheduledSectionIds)->map(function ($unassignedId) {
                return ['section_id' => $unassignedId, 'reason' => 'no available room/timeslot'];
            })->values()->toArray();

            // Persist the schedule with duplicate check
            foreach ($finalSchedule as $scheduleEntry) {
                // Check if schedule already exists to avoid duplicates
                $exists = Schedule::where('section_id', $scheduleEntry['section_id'])
                    ->where('room_id', $scheduleEntry['room_id'])
                    ->where('timeslot_id', $scheduleEntry['timeslot_id'])
                    ->exists();
                
                if (!$exists) {
                    Schedule::create($scheduleEntry);
                }
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Schedule generated successfully using simple engine',
                'scheduled' => count($finalSchedule),
                'total_sections' => $this->sections->count(),
                'scheduled_sections' => $scheduledSectionIds->count(),
                'conflicts' => $this->unscheduledSections,
                'day_distribution' => $this->getDayDistributionStats(),
                'partial_schedule' => $this->unscheduledSections > 0,
                'execution_time' => round(microtime(true) - $this->startTime, 2),
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Schedule generation failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Schedule generation failed: ' . $e->getMessage(),
                'execution_time' => round(microtime(true) - $this->startTime, 2),
            ];
        }
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
        
        // PRUNING: Check if remaining sections can possibly be scheduled
        if (!$this->hasEnoughRemainingCapacity($sectionIndex, $requiredSlots)) {
            return false;
        }
        
        // Get all possible timeslot-room combinations for this section
        $possibleAssignments = $this->getPossibleAssignments($section, $course, $availableTimeslots, $requiredSlots);
        
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
            // Forward checking: test if this assignment leads to dead end
            if (!$this->forwardCheck($sectionIndex, $assignment)) {
                continue;
            }
            
            // Apply assignment
            $this->applyAssignment($section, $assignment);
            
            // Recurse to next section
            $result = $this->backtrackAssignSections($sectionIndex + 1, $availableTimeslots);
            
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
        $totalRequiredSlots = $remainingSections->sum(function($section) {
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

            if (!$found) {
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

        if ($validTimeslots->count() < $requiredSlots) {
            return $possibilities;
        }

        $combinations = $this->getValidCombinationsByDay($validTimeslots, $requiredSlots);

        foreach ($combinations as $combination) {
            $eligibleRooms = $this->getEligibleRooms($course);

            foreach ($eligibleRooms as $room) {
                if ($room->capacity < $section->capacity) {
                    continue;
                }

                $roomAvailable = true;
                foreach ($combination as $timeslot) {
                    if ($this->hasRoomConflictInMemory($room, $timeslot)) {
                        $roomAvailable = false;
                        break;
                    }
                }

                if (!$roomAvailable) {
                    continue;
                }

                $day = $combination[0]->day_of_week;
                $score = $this->calculateAssignmentScore($section, $course, $combination, $room);

                $possibilities->push([
                    'timeslots' => $combination,
                    'room' => $room,
                    'day' => $day,
                    'score' => $score,
                ]);

                break; // Use first available room for this combination
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
     * Find available room
     */
    protected function findAvailableRoom($section, $course, $timeslot)
    {
        $eligibleRooms = $this->getEligibleRooms($course);
        
        // Sort rooms by usage count (least used first)
        $eligibleRooms = $eligibleRooms->sortBy(function($room) {
            return count($this->roomTimeslotMap[$room->id] ?? []);
        });
        
        foreach ($eligibleRooms as $room) {
            if ($room->capacity < $section->capacity) {
                continue;
            }
            if (!$this->isRoomSuitableForCourse($room, $course)) {
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
        $totalRequired = $this->sections->sum(function($s) {
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
                                ->whereHas('courseOffering', function($q) {
                                    $q->where('semester_id', $this->semesterId);
                                })
                                ->get()
                                ->filter(function ($section) {
                                    return $section->courseOffering && $section->courseOffering->course;
                                });

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

        // Exclude sections with no eligible rooms as conflicts (still allow others to be scheduled)
        $noRoomSections = $this->sections->filter(function ($section) {
            return $this->getEligibleRooms($section->courseOffering->course)->isEmpty();
        });

        if ($noRoomSections->isNotEmpty()) {
            foreach ($noRoomSections as $section) {
                $this->conflictDetails[] = [
                    'type' => 'no_eligible_room',
                    'section_id' => $section->id,
                    'course' => $section->courseOffering->course->course_name,
                    'message' => "No eligible room for section {$section->section_name}"
                ];
            }

            $this->sections = $this->sections->reject(function ($section) use ($noRoomSections) {
                return $noRoomSections->contains($section);
            })->values();
        }

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
        $totalRequiredSlots = $this->sections->sum(function($section) {
            return $section->courseOffering->course->hours_per_week ?? 3;
        });
        $totalCapacity = $this->timeslots->count() * $this->rooms->count();
        
        if ($totalRequiredSlots > $totalCapacity) {
            throw new \Exception("Impossible: Not enough total capacity for all required slots. Required: {$totalRequiredSlots}, Available: {$totalCapacity}");
        }
        
        // Check each section has at least one teacher
        foreach ($this->sections as $section) {
            if ($section->teachers->count() === 0) {
                throw new \Exception('Impossible: No teacher assigned for section ' . $section->id);
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

        // Ensure every section has at least one teacher
        $defaultTeacher = $this->teachers->first();
        foreach ($this->sections as $section) {
            if ($section->teachers->isEmpty()) {
                if ($defaultTeacher) {
                    $section->setRelation('teachers', collect([$defaultTeacher]));
                } else {
                    throw new \Exception('No teachers assigned and no available teachers to fallback.');
                }
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
            if (!$this->hasTeacherHoursAvailable($teacher, $section->courseOffering->course, 1)) {
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
        if ($this->hasAnyTeacherConflict($section, $timeslot)) {
            return false;
        }
        
        if ($this->hasAnyStudentConflict($section, $timeslot)) {
            return false;
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
                $defaultTimeslots->push((object)[
                    'id' => $counter++,
                    'day_of_week' => $day,
                    'start_time' => $time['start'],
                    'end_time' => $time['end']
                ]);
            }
        }
        
        return $defaultTimeslots;
    }
}
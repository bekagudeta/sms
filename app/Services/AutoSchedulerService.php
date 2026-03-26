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
    protected $studentGroupTimeslotMap = [];
    protected $teacherHours = [];
    protected $sectionAssignments = [];
    
    // Precomputed data for performance
    protected $sectionStudentIds = [];
    protected $roomCache = [];
    protected $teacherCourseMap = [];
    protected $availableTimeslotsCache = [];
    
    // Best solution tracking
    protected $bestSchedule = null;
    protected $bestScore = -INF;
    protected $currentSchedule = [];
    // Removed: protected $currentConflicts = [];
    protected $bestPartialSchedule = [];
    protected $bestPartialScore = -INF;
    
    // Performance & configuration
    protected $maxAttempts = 10;
    protected $maxBacktrackSteps = 50000;
    protected $backtrackCount = 0;
    protected $timeLimitSeconds = 30;
    protected $startTime;
    protected $maxCombinationsPerSection = 100; // Limit combinatorial explosion
    
    // Soft constraint weights
    protected $weights = [
        'teacher_load_balance' => 10,
        'room_preference' => 5,
        'timeslot_preference' => 3,
        'student_distribution' => 2,
        'compact_schedule' => 4,
    ];

    public function generateSchedule($semesterId)
    {
        $this->startTime = microtime(true);
        $this->semesterId = $semesterId;

        DB::beginTransaction();

        try {
            // Clear existing schedules
            Schedule::whereHas('section.courseOffering', function ($q) {
                $q->where('semester_id', $this->semesterId);
            })->delete();

            // Load and prepare data
            $this->loadData();
            $this->validateData();
            $this->precomputeData();
            
            // Sort sections by difficulty
            $this->sections = $this->sortSectionsByDifficulty();
            
            // Run multiple attempts to find best schedule
            $bestResult = null;
            for ($attempt = 0; $attempt < $this->maxAttempts; $attempt++) {
                $this->resetSchedulingState();
                $this->currentSchedule = [];
                // Removed: $this->currentConflicts = [];
                $this->backtrackCount = 0;
                
                // Shuffle timeslots for variety between attempts
                $shuffledTimeslots = $this->timeslots->shuffle();
                
                // Run backtracking solver
                $success = $this->backtrackAssignSections(0, $shuffledTimeslots);
                
                $currentScore = $this->getCurrentScore();
                
                // Fix: Compare correctly (higher score is better)
                if ($success && $currentScore > $this->bestScore) {
                    $this->bestSchedule = $this->currentSchedule;
                    $this->bestScore = $currentScore;
                    $bestResult = $this->prepareResult(true);
                } elseif (!$success && $currentScore > $this->bestPartialScore) {
                    // Store best partial solution
                    $this->bestPartialSchedule = $this->currentSchedule;
                    $this->bestPartialScore = $currentScore;
                }
                
                // Early exit if we found a perfect solution
                if ($success && count($this->currentSchedule) === $this->sections->sum(function($s) {
                    return $s->courseOffering->course->hours_per_week ?? 3;
                })) {
                    break;
                }
                
                // Check time limit
                if (microtime(true) - $this->startTime > $this->timeLimitSeconds) {
                    break;
                }
            }
            
            // Use best schedule found, or fall back to partial
            $finalSchedule = $this->bestSchedule ?? $this->bestPartialSchedule;
            
            if (!$finalSchedule || empty($finalSchedule)) {
                throw new \Exception("Could not generate any schedule after {$this->maxAttempts} attempts");
            }
            
            // Persist the schedule
            foreach ($finalSchedule as $scheduleEntry) {
                Schedule::create($scheduleEntry);
            }
            
            DB::commit();
            
            $result = $this->prepareResult(!empty($this->bestSchedule));
            $result['partial_schedule'] = empty($this->bestSchedule);
            return $result;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::warning('Auto scheduling failed, attempting fallback: ' . $e->getMessage());

            // Fallback using simpler engine when complex auto scheduler fails
            try {
                $engine = new SchedulingEngine();
                $engineResult = $engine->generateSchedule($this->semesterId);

                if ($engineResult['success'] || !empty($engineResult['assignments'])) {
                    // Clear existing schedules for this semester before fallback persistence
                    Schedule::whereHas('section.courseOffering', function ($q) {
                        $q->where('semester_id', $this->semesterId);
                    })->delete();

                    foreach ($engineResult['assignments'] as $assignment) {
                        Schedule::create([
                            'section_id' => $assignment['section_id'],
                            'room_id' => $assignment['room_id'],
                            'timeslot_id' => $assignment['timeslot_id'],
                        ]);
                    }

                    return [
                        'success' => true,
                        'message' => 'Fallback scheduler completed with ' . count($engineResult['assignments']) . ' assignments',
                        'scheduled' => count($engineResult['assignments']),
                        'conflicts' => $engineResult['conflicts'] ?? [],
                        'partial_schedule' => !$engineResult['success']
                    ];
                }
            } catch (\Exception $fallbackException) {
                Log::error('Fallback scheduling also failed: ' . $fallbackException->getMessage());
            }

            return [
                'success' => false,
                'message' => 'Auto scheduling failed: ' . $e->getMessage(),
                'attempts' => $this->maxAttempts,
                'backtrack_steps' => $this->backtrackCount,
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
        
        // Precompute teacher qualifications
        foreach ($this->teachers as $teacher) {
            $this->teacherCourseMap[$teacher->id] = [];
        }
        
        // Precompute room availability cache
        foreach ($this->rooms as $room) {
            $this->roomCache[$room->id] = [];
        }
        
        // Precompute available timeslots per day/time combination
        $this->availableTimeslotsCache = $this->timeslots->groupBy('day_of_week');
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
            
            // Factor 4: Room constraints with caching
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
            $teacher = $section->teachers->sortBy(function($t) {
                return $this->teacherHours[$t->id] ?? 0;
            })->first();
            if ($teacher) {
                $teacherLoad[$teacher->id] = ($this->teacherHours[$teacher->id] ?? 0) + ($section->courseOffering->course->hours_per_week ?? 3);
                $maxHours = $teacher->max_hours_per_week ?? 20;
                if ($teacherLoad[$teacher->id] > $maxHours) {
                    return false;
                }
            }
        }
        // Room-type capacity check
        $roomTypeDemand = [];
        foreach ($remainingSections as $section) {
            $course = $section->courseOffering->course;
            $type = $course->required_room_type ?? 'lecture';
            $roomTypeDemand[$type] = ($roomTypeDemand[$type] ?? 0) + ($course->hours_per_week ?? 3);
        }
        foreach ($roomTypeDemand as $type => $demand) {
            $roomCount = $this->rooms->filter(function($room) use ($type) {
                return ($room->type ?? 'lecture') === $type;
            })->count();
            $typeCapacity = $this->timeslots->count() * $roomCount;
            if ($demand > $typeCapacity) {
                return false;
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
            // Get valid timeslots
            $validTimeslots = $this->timeslots->filter(function ($timeslot) use ($remainingSection, $course) {
                return $this->isTimeslotValid($remainingSection, $course, $timeslot)
                    && $this->findAvailableRoomWithCache($remainingSection, $course, $timeslot) !== null;
            });
            if ($validTimeslots->count() < $requiredSlots) {
                return false; // Not enough valid slots
            }
            // Try to generate at least one valid combination
            $candidateTimeslots = $this->getPromisingTimeslots($validTimeslots, $requiredSlots);
            $combinations = $this->generateSmartCombinations($candidateTimeslots, $requiredSlots);
            $found = false;
            foreach ($combinations as $combination) {
                $rooms = [];
                $valid = true;
                foreach ($combination as $timeslot) {
                    $room = $this->findAvailableRoomWithCache($remainingSection, $course, $timeslot);
                    if (!$room) {
                        $valid = false;
                        break;
                    }
                    $rooms[] = $room;
                }
                if ($valid) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false; // No valid combination exists
            }
        }
        return true;
    }
    
    /**
     * Get all possible timeslot-room combinations with scoring
     */
    protected function getPossibleAssignments($section, $course, $availableTimeslots, $requiredSlots)
    {
        $possibilities = collect();
        
        // Get valid timeslots first
        $validTimeslots = $availableTimeslots->filter(function ($timeslot) use ($section, $course) {
            return $this->isTimeslotValid($section, $course, $timeslot);
        });
        
        if ($validTimeslots->count() < $requiredSlots) {
            return $possibilities;
        }
        
        // Smart combination generation: limit to most promising timeslots
        $candidateTimeslots = $this->getPromisingTimeslots($validTimeslots, $requiredSlots);
        
        // Generate combinations with room pairing
        $combinations = $this->generateSmartCombinations($candidateTimeslots, $requiredSlots);
        
        foreach ($combinations as $combination) {
            // Enforce consecutive slots and same room for multi-slot assignments
            $rooms = [];
            $valid = true;
            $lastRoom = null;
            foreach ($combination as $idx => $timeslot) {
                $room = $this->findAvailableRoomWithCache($section, $course, $timeslot);
                if (!$room) {
                    $valid = false;
                    break;
                }
                // Enforce same room for all slots if possible
                if ($idx === 0) {
                    $lastRoom = $room;
                } else if ($lastRoom && $room->id !== $lastRoom->id) {
                    $valid = false;
                    break;
                }
                // Enforce consecutive slots (same day, adjacent times)
                if ($idx > 0 && isset($combination[$idx-1])) {
                    $prevSlot = $combination[$idx-1];
                    if (!is_object($prevSlot) || !is_object($timeslot)) {
                        // skip check if either is not an object
                        continue;
                    }
                    if (isset($prevSlot->day_of_week) && isset($timeslot->day_of_week) && $timeslot->day_of_week !== $prevSlot->day_of_week) {
                        $valid = false;
                        break;
                    }
                    if (isset($prevSlot->end_time) && isset($timeslot->start_time) && $prevSlot->end_time !== $timeslot->start_time) {
                        $valid = false;
                        break;
                    }
                }
                $rooms[] = $room;
            }
            if ($valid) {
                $score = $this->calculateAssignmentScore($section, $course, $combination, $rooms);
                $possibilities->push([
                    'timeslots' => $combination,
                    'rooms' => $rooms,
                    'score' => $score,
                ]);
            }
        }
        
        return $possibilities;
    }
    
    /**
     * Get most promising timeslots to reduce combinations
     */
    protected function getPromisingTimeslots($validTimeslots, $requiredSlots)
    {
        // Score each timeslot
        $scoredTimeslots = $validTimeslots->map(function ($timeslot) {
            $score = 0;
            // Prefer middle of day times
            $hour = (int) substr($timeslot->start_time, 0, 2);
            if ($hour >= 9 && $hour <= 16) {
                $score += 10;
            }
            // Prefer less used timeslots
            $usageCount = $this->getTimeslotUsageCount($timeslot);
            $score -= $usageCount * 2;
            // Prefer consecutive slots (by time proximity, simple heuristic)
            $score += (int) str_replace(':', '', $timeslot->start_time) * 0.01;
            return ['timeslot' => $timeslot, 'score' => $score];
        })->sortByDesc('score');
        // Take top N timeslots for combination generation
        $maxTimeslots = min($scoredTimeslots->count(), $requiredSlots * 3);
        return $scoredTimeslots->take($maxTimeslots)->pluck('timeslot');
    }
    
    /**
     * Generate smart combinations with pruning
     */
    protected function generateSmartCombinations($timeslots, $requiredSlots)
    {
        if ($requiredSlots <= 0) {
            return [[]];
        }
        
        $combinations = [];
        $timeslotArray = $timeslots->values()->toArray();
        $this->generateCombinationsWithPruning($timeslotArray, $requiredSlots, 0, [], $combinations);
        
        return collect($combinations);
    }
    
    protected function generateCombinationsWithPruning($timeslots, $k, $start, $current, &$result)
    {
        if (count($current) == $k) {
            $result[] = $current;
            return;
        }
        
        // Prune if not enough remaining elements
        if (($start + ($k - count($current))) > count($timeslots)) {
            return;
        }
        
        for ($i = $start; $i < count($timeslots); $i++) {
            // Avoid duplicate days in same combination for better distribution
            if (count($current) > 0 && $timeslots[$i]->day_of_week === end($current)->day_of_week) {
                continue;
            }
            
            $current[] = $timeslots[$i];
            $this->generateCombinationsWithPruning($timeslots, $k, $i + 1, $current, $result);
            array_pop($current);
        }
    }
    
    /**
     * Check if a timeslot is valid for section and course
     */
    protected function isTimeslotValid($section, $course, $timeslot)
    {
        // Hard constraints check
        if ($this->hasAnyTeacherConflict($section, $timeslot)) {
            return false;
        }
        
        if ($this->hasAnyStudentConflict($section, $timeslot)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Find available room with caching
     */
    protected function findAvailableRoomWithCache($section, $course, $timeslot)
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
     * Calculate comprehensive score for an assignment
     */
    protected function calculateAssignmentScore($section, $course, $timeslots, $rooms)
    {
        $score = 0;
        
        foreach ($timeslots as $index => $timeslot) {
            // Teacher load balance
            foreach ($section->teachers as $teacher) {
                $currentHours = $this->teacherHours[$teacher->id] ?? 0;
                $score += max(0, (20 - $currentHours)) * $this->weights['teacher_load_balance'];
            }
            
            // Timeslot preference (avoid early morning/late evening)
            $hour = (int) substr($timeslot->start_time, 0, 2);
            if ($hour >= 9 && $hour <= 16) {
                $score += $this->weights['timeslot_preference'];
            } elseif ($hour < 8 || $hour > 18) {
                $score -= 5; // Penalty for very early/late
            }
            
            // Room suitability
            $room = $rooms[$index];
            if ($room->type === ($course->preferred_room_type ?? 'lecture')) {
                $score += $this->weights['room_preference'];
            }
            
            // Distribution across week
            if ($index > 0 && $timeslots[$index]->day_of_week !== $timeslots[$index - 1]->day_of_week) {
                $score += $this->weights['student_distribution'];
            }
        }
        
        // Bonus for compact schedule (all slots within 3 days)
        $uniqueDays = collect($timeslots)->pluck('day_of_week')->unique()->count();
        if ($uniqueDays <= 3) {
            $score += $this->weights['compact_schedule'];
        }
        
        return $score;
    }
    
    /**
     * Apply assignment to in-memory state - tries ALL teachers to find best fit
     */
    protected function applyAssignment($section, &$assignment)
    {
        $timeslots = $assignment['timeslots'];
        $rooms = $assignment['rooms'];
        
        // Try ALL teachers and pick the one with least load that fits
        $bestTeacher = null;
        $bestLoad = PHP_INT_MAX;
        
        foreach ($section->teachers as $teacher) {
            $load = $this->teacherHours[$teacher->id] ?? 0;
            if ($load < $bestLoad) {
                // Verify this teacher has no conflict with any of the timeslots
                $hasConflict = false;
                foreach ($timeslots as $timeslot) {
                    if (isset($this->teacherTimeslotMap[$teacher->id][$timeslot->id])) {
                        $hasConflict = true;
                        break;
                    }
                }
                if (!$hasConflict && $this->hasTeacherHoursAvailable($teacher, $section->courseOffering->course, count($timeslots))) {
                    $bestLoad = $load;
                    $bestTeacher = $teacher;
                }
            }
        }
        
        $assignment['teacher'] = $bestTeacher;
        foreach ($timeslots as $index => $timeslot) {
            $room = $rooms[$index];
            $this->currentSchedule[] = [
                'section_id' => $section->id,
                'room_id' => $room->id,
                'timeslot_id' => $timeslot->id,
            ];
            // Update teacher maps
            if ($bestTeacher) {
                $this->teacherTimeslotMap[$bestTeacher->id][$timeslot->id] = true;
                $this->teacherHours[$bestTeacher->id] = ($this->teacherHours[$bestTeacher->id] ?? 0) + 1;
            }
            // Update room maps
            $this->roomTimeslotMap[$room->id][$timeslot->id] = true;
            // Update student maps
            foreach ($this->sectionStudentIds[$section->id] ?? [] as $studentId) {
                $this->studentGroupTimeslotMap[$studentId][$timeslot->id] = true;
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
        $rooms = $assignment['rooms'];
        $teacher = isset($assignment['teacher']) ? $assignment['teacher'] : null;
        foreach ($timeslots as $index => $timeslot) {
            $room = $rooms[$index];
            array_pop($this->currentSchedule);
            // Remove from teacher maps
            if ($teacher) {
                unset($this->teacherTimeslotMap[$teacher->id][$timeslot->id]);
                $this->teacherHours[$teacher->id] = max(0, ($this->teacherHours[$teacher->id] ?? 0) - 1);
                if (($this->teacherHours[$teacher->id] ?? 0) == 0) {
                    unset($this->teacherHours[$teacher->id]);
                }
            }
            // Remove from room maps
            unset($this->roomTimeslotMap[$room->id][$timeslot->id]);
            // Remove from student maps
            foreach ($this->sectionStudentIds[$section->id] ?? [] as $studentId) {
                unset($this->studentGroupTimeslotMap[$studentId][$timeslot->id]);
            }
        }
        unset($this->sectionAssignments[$section->id]);
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
            $score += max(0, 20 - $hours) * 10;
        }
        
        // Room utilization score (reward efficient usage)
        foreach ($this->roomTimeslotMap as $roomTimeslots) {
            $score += count($roomTimeslots) * 5;
        }
        
        // Penalize unscheduled sections
        $scheduledSections = count($this->sectionAssignments);
        $totalSections = $this->sections->count();
        $score -= ($totalSections - $scheduledSections) * 100;
        
        return $score;
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
        
        return [
            'success' => $success,
            'message' => $success ? 'Schedule generated successfully' : 'Partial schedule generated',
            'scheduled' => count($this->bestSchedule ?? $this->currentSchedule),
            'total_required' => $totalRequired,
            'completion_rate' => round((count($this->bestSchedule ?? $this->currentSchedule) / max(1, $totalRequired)) * 100, 2),
            // 'conflicts' => count($this->currentConflicts), // removed: not implemented
            'total_sections' => $this->sections->count(),
            'backtrack_steps' => $this->backtrackCount,
            'execution_time' => round($executionTime, 2),
            'best_score' => $this->bestScore,
            'attempts' => $this->maxAttempts,
        ];
    }
    
    // ========== EXISTING HELPER METHODS ==========
    
    protected function loadData()
    {
        $this->sections = Section::with(['courseOffering.course', 'courseOffering.semester', 'teachers', 'enrollments:section_id,student_id'])
                                ->whereHas('courseOffering', function($q) {
                                    $q->where('semester_id', $this->semesterId);
                                })
                                ->get()
                                ->filter(function ($section) {
                                    return $section->courseOffering && $section->courseOffering->course;
                                });

        $this->teachers = Teacher::all();
        $this->rooms = Room::all();
        $this->timeslots = Timeslot::whereIn('day_of_week', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])->get();

        
        if ($this->timeslots->isEmpty()) {
            $this->timeslots = $this->createDefaultTimeslots();
        }
        
        $this->resetSchedulingState();
        // --- FEASIBILITY PRE-CHECKS ---
        // 1. Check total required slots vs total capacity
        $totalRequiredSlots = $this->sections->sum(function($section) {
            return $section->courseOffering->course->hours_per_week ?? 3;
        });
        $totalCapacity = $this->timeslots->count() * $this->rooms->count();
        if ($totalRequiredSlots > $totalCapacity) {
            throw new \Exception('Impossible: Not enough total capacity for all required slots.');
        }
        // 2. Check at least one eligible room per section
        foreach ($this->sections as $section) {
            $course = $section->courseOffering->course;
            if ($this->getEligibleRooms($course)->count() === 0) {
                throw new \Exception('Impossible: No eligible room for section ' . $section->id);
            }
            // 3. Check at least one teacher per section
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

        // Ensure every section has at least one teacher; assign fallback if missing.
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
            if (isset($room->capacity) && isset($course->student_count) && $room->capacity < $course->student_count) {
                return false;
            }
            
            if (isset($course->required_room_type) && isset($room->type) && $course->required_room_type !== $room->type) {
                return false;
            }
            
            return true;
        })->values();
    }
    
    protected function hasAnyTeacherConflict($section, $timeslot)
    {
        foreach ($section->teachers as $teacher) {
            if (isset($this->teacherTimeslotMap[$teacher->id][$timeslot->id])) {
                return true;
            }
            if (!$this->hasTeacherHoursAvailable($teacher, $section->courseOffering->course)) {
                return true;
            }
        }
        return false;
    }
    
    protected function hasAnyStudentConflict($section, $timeslot)
    {
        // Optimized with precomputed student IDs
        foreach ($this->sectionStudentIds[$section->id] ?? [] as $studentId) {
            if (isset($this->studentGroupTimeslotMap[$studentId][$timeslot->id])) {
                return true;
            }
        }
        return false;
    }
    
    protected function hasTeacherHoursAvailable($teacher, $course, $slotsNeeded = 1)
    {
        $currentHours = $this->teacherHours[$teacher->id] ?? 0;
        $maxHours = $teacher->max_hours_per_week ?? 20;
        return ($currentHours + $slotsNeeded) <= $maxHours;
    }
    
    protected function hasRoomConflictInMemory($room, $timeslot)
    {
        return isset($this->roomTimeslotMap[$room->id][$timeslot->id]);
    }
    
    protected function isRoomSuitableForCourse($room, $course)
    {
        $roomType = $room->type ?? 'lecture';
        
        if (str_contains(strtolower($course->course_name ?? ''), 'lab') && $roomType !== 'lab') {
            return false;
        }
        
        return true;
    }
    
    protected function resetSchedulingState()
    {
        $this->teacherTimeslotMap = [];
        $this->roomTimeslotMap = [];
        $this->studentGroupTimeslotMap = [];
        $this->teacherHours = [];
        $this->sectionAssignments = [];
        $this->currentSchedule = [];
        // $this->currentConflicts = []; // removed: not implemented
        $this->roomCache = [];
    }
    
    protected function createDefaultTimeslots()
    {
        $defaultTimeslots = collect();
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $times = [
            ['start' => '08:00', 'end' => '09:30'],
            ['start' => '10:00', 'end' => '11:30'],
            ['start' => '12:00', 'end' => '13:30'],
            ['start' => '14:00', 'end' => '15:30'],
            ['start' => '16:00', 'end' => '17:30'],
        ];
        
        foreach ($days as $day) {
            foreach ($times as $time) {
                $defaultTimeslots->push((object)[
                    'id' => $day . '_' . $time['start'],
                    'day_of_week' => $day,
                    'start_time' => $time['start'],
                    'end_time' => $time['end']
                ]);
            }
        }
        
        return $defaultTimeslots;
    }
}
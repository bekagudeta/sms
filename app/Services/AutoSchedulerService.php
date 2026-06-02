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
    protected $teacherTimeslotMap  = [];
    protected $roomTimeslotMap     = [];
    protected $studentTimeslotMap  = [];

    /**
     * Per-teacher accumulated teaching hours (1 unit per scheduled timeslot).
     * This is the single source of truth for workload tracking.
     */
    protected $teacherHours = [];

    protected $sectionAssignments = [];
    protected $sectionRoomMap     = [];
    protected $roomSectionsMap    = [];

    // Conflict tracking
    protected $conflictCount   = 0;
    protected $conflictDetails = [];

    // Day distribution tracking
    protected $dayUsageCount       = [];
    protected $teacherDayUsage     = [];
    protected $teacherScheduledDays = [];

    // Timeslots grouped by day
    protected $timeslotsByDay = [];

    protected $unscheduledSections = 0;

    // Precomputed data
    protected $sectionStudentIds          = [];
    protected $roomCache                  = [];
    protected $teacherCourseMap           = [];
    protected $availableTimeslotsCache    = [];
    protected $timeslotOrderMap           = [];
    protected $warnedRoomFallbackCourses  = [];

    protected $completionRelaxedStudents = false;

    protected $totalSectionCount = 0;

    // Best solution tracking
    protected $bestSchedule        = null;
    protected $bestScore           = -INF;
    protected $currentSchedule     = [];
    protected $bestPartialSchedule = [];
    protected $bestPartialScore    = -INF;

    // Performance & configuration
    protected $maxAttempts               = 3;
    protected $maxBacktrackSteps         = 5000;
    protected $backtrackCount            = 0;
    protected $timeLimitSeconds          = 30;
    protected $startTime;
    protected $maxCombinationsPerSection = 100;

    // Soft constraint weights
    protected $weights = [
        'teacher_load_balance' => 10,
        'room_preference'      => 5,
        'timeslot_preference'  => 3,
        'student_distribution' => 2,
        'compact_schedule'     => 4,
        'day_distribution'     => 8,
    ];

    // =========================================================================
    //  PUBLIC ENTRY POINT
    // =========================================================================

    public function generateSchedule($semesterId)
    {
        $this->startTime  = microtime(true);
        $this->semesterId = $semesterId;

        DB::beginTransaction();

        try {
            Schedule::whereHas('section.courseOffering', function ($q) {
                $q->where('semester_id', $this->semesterId);
            })->delete();

            $this->completionRelaxedStudents = false;

            $this->loadData();
            $this->validateData();
            $this->precomputeData();

            $requiredSlotsBySection = $this->getRequiredSlotsBySection();

            // ── try the simple engine first ───────────────────────────────
            Log::info('Attempting simple scheduling engine first');
            $engine        = new SchedulingEngine;
            $engineResult  = $engine->generateSchedule($this->semesterId);
            $simpleSchedule = $this->filterSemesterSchedule($engineResult['assignments'] ?? []);
            $simpleCoverage = $this->analyzeScheduleCoverage($simpleSchedule, $requiredSlotsBySection);

            $simpleValidationErrors = $simpleCoverage['complete']
                ? $this->validateGeneratedSchedule($simpleSchedule)
                : [];

            $finalSchedule = $simpleSchedule;
            $coverage      = $simpleCoverage;
            $engineUsed    = 'simple';

            if (!$coverage['complete'] || !empty($simpleValidationErrors)) {
                Log::info('Simple engine incomplete; trying advanced backtracking', [
                    'missing_sections'  => count($coverage['missing']),
                    'validation_errors' => count($simpleValidationErrors),
                    'scheduled_slots'   => $coverage['scheduled_slots'],
                    'required_slots'    => $coverage['required_slots'],
                ]);

                $advancedSchedule = $this->attemptAdvancedScheduling();
                $advancedCoverage = $this->analyzeScheduleCoverage($advancedSchedule, $requiredSlotsBySection);

                if ($advancedCoverage['scheduled_slots'] > $coverage['scheduled_slots']) {
                    $finalSchedule = $advancedSchedule;
                    $coverage      = $advancedCoverage;
                    $engineUsed    = 'advanced';
                }
            }

            if (!$coverage['complete']) {
                Log::info('Attempting completion pass for missing slots', [
                    'missing_sections' => count($coverage['missing']),
                ]);

                $finalSchedule = $this->attemptFillMissingSlots($finalSchedule, $requiredSlotsBySection);
                $coverage      = $this->analyzeScheduleCoverage($finalSchedule, $requiredSlotsBySection);
                if (!$coverage['complete']) {
                    $engineUsed = $engineUsed.'+fill';
                }
            }

            if (!$coverage['complete']) {
                $this->conflictDetails = $coverage['missing'];
                throw new \Exception($this->formatIncompleteScheduleMessage($coverage));
            }

            $validationErrors = $this->validateGeneratedSchedule($finalSchedule);
            $blockingErrors   = $this->filterBlockingValidationErrors($validationErrors);

            if (!empty($blockingErrors)) {
                $this->conflictDetails = $blockingErrors;
                throw new \Exception('Generated schedule has conflicts: '.$blockingErrors[0]['message']);
            }

            if (!empty($validationErrors) && empty($blockingErrors)) {
                Log::warning('Schedule generated with non-blocking warnings', [
                    'warnings' => $validationErrors,
                ]);
            }

            $finalSchedule = $this->normalizeScheduleEntries($finalSchedule);
            if (empty($finalSchedule)) {
                throw new \Exception($this->formatIncompleteScheduleMessage($coverage));
            }

            $scheduledSectionIds = collect($finalSchedule)->pluck('section_id')->unique();
            $this->sectionAssignments = $scheduledSectionIds
                ->mapWithKeys(fn ($id) => [$id => true])
                ->toArray();
            $this->unscheduledSections = 0;
            $this->conflictCount       = 0;
            $this->conflictDetails     = [];

            foreach ($finalSchedule as $scheduleEntry) {
                Schedule::create($scheduleEntry);
            }

            DB::commit();

            return [
                'success'            => true,
                'message'            => 'Schedule generated successfully using '.$engineUsed.' engine',
                'scheduled'          => count($finalSchedule),
                'total_sections'     => $this->totalSectionCount,
                'scheduled_sections' => $scheduledSectionIds->count(),
                'required_slots'     => $coverage['required_slots'],
                'scheduled_slots'    => $coverage['scheduled_slots'],
                'conflicts'          => 0,
                'day_distribution'   => $this->getDayDistributionStats(),
                'partial_schedule'   => false,
                'engine'             => $engineUsed,
                'execution_time'     => round(microtime(true) - $this->startTime, 2),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Schedule generation failed: '.$e->getMessage());

            return [
                'success'        => false,
                'message'        => 'Schedule generation failed: '.$e->getMessage(),
                'execution_time' => round(microtime(true) - $this->startTime, 2),
            ];
        }
    }

    public function getConflicts()
    {
        return $this->conflictDetails ?? [];
    }

    // =========================================================================
    //  CREDIT-HOUR RULE  (single authoritative implementation)
    // =========================================================================

    /**
     * Number of timeslot-hours a course must be assigned per week.
     *
     * Rule:  1 credit hour  →  1 teaching hour per week  (1 : 1)
     *        2 credit hours →  2 teaching hours per week
     *        … and so on.
     *
     * Falls back to hours_per_week only when credits is absent/zero.
     */
    protected function getRequiredWeeklyHours($course): int
    {
        $credits = (int) ($course->credits ?? 0);
        if ($credits > 0) {
            return $credits;
        }

        $hoursPerWeek = (int) ($course->hours_per_week ?? 0);
        if ($hoursPerWeek > 0) {
            return $hoursPerWeek;
        }

        Log::warning('Course has neither credits nor hours_per_week; defaulting to 3.', [
            'course_id'   => $course->id   ?? null,
            'course_name' => $course->course_name ?? null,
        ]);
        return 3;
    }

    /**
     * Hard weekly teaching-hour cap for a teacher (never above 38).
     */
    protected function getTeacherMaxWeeklyHours($teacher): int
    {
        return min(
            (int) ($teacher->max_hours_per_week ?? config('scheduling.max_teacher_hours_per_week', 38)),
            (int) config('scheduling.max_teacher_hours_per_week', 38)
        );
    }

    /**
     * Can this teacher accept one more scheduled timeslot (= 1 more hour)?
     *
     * We always add exactly 1 here because the scheduler assigns timeslots
     * one-at-a-time; the credit-hour count of the course determines how many
     * timeslots will be assigned in total, not how much each one costs.
     */
    protected function teacherCanAcceptOneMoreHour($teacher): bool
    {
        $current = $this->teacherHours[$teacher->id] ?? 0;
        return ($current + 1) <= $this->getTeacherMaxWeeklyHours($teacher);
    }

    // =========================================================================
    //  REQUIRED SLOTS PER SECTION  (used by coverage analysis)
    // =========================================================================

    protected function getRequiredSlotsBySection()
    {
        return $this->sections->mapWithKeys(function ($section) {
            return [
                $section->id => $this->getRequiredWeeklyHours($section->courseOffering->course),
            ];
        });
    }

    // =========================================================================
    //  COVERAGE & VALIDATION
    // =========================================================================

    protected function analyzeScheduleCoverage(array $assignments, $requiredSlotsBySection): array
    {
        $slotCounts = collect($assignments)->countBy('section_id');
        $missing    = [];

        foreach ($requiredSlotsBySection as $sectionId => $requiredSlots) {
            $scheduledSlots = (int) ($slotCounts[$sectionId] ?? 0);
            if ($scheduledSlots < $requiredSlots) {
                $section   = $this->sections->firstWhere('id', $sectionId);
                $missing[] = [
                    'type'            => 'insufficient_section_slots',
                    'section_id'      => $sectionId,
                    'section_name'    => $section?->section_name,
                    'course'          => $section?->courseOffering?->course?->course_name,
                    'required_slots'  => $requiredSlots,
                    'scheduled_slots' => $scheduledSlots,
                    'message'         => "Section {$section?->section_name} needs {$requiredSlots} weekly slot(s), "
                                       . "but only {$scheduledSlots} were scheduled.",
                ];
            }
        }

        return [
            'complete'        => empty($missing),
            'missing'         => $missing,
            'required_slots'  => (int) collect($requiredSlotsBySection)->sum(),
            'scheduled_slots' => count($assignments),
        ];
    }

    /**
     * Validate the fully-assembled schedule for hard-constraint violations.
     *
     * Teacher workload is checked as: total scheduled slots for that teacher
     * must not exceed their weekly hour cap.  Each timeslot == 1 hour.
     */
    protected function validateGeneratedSchedule(array $assignments): array
    {
        $errors           = [];
        $roomTimeslots    = [];
        $sectionTimeslots = [];
        $roomSections     = [];
        $sectionSlotCounts = [];
        $teacherTimeslots = [];
        $teacherSlotCount = [];   // raw slot count per teacher (= hours)
        $teacherModels    = [];
        $studentTimeslots = [];

        $sections = $this->sections->keyBy('id');
        $rooms    = $this->rooms->keyBy('id');

        foreach ($assignments as $assignment) {
            $section    = $sections->get($assignment['section_id']);
            $room       = $rooms->get($assignment['room_id']);
            $timeslotId = (int) $assignment['timeslot_id'];

            if (!$section || !$room) {
                $errors[] = [
                    'type'    => 'invalid_assignment_reference',
                    'message' => 'Generated schedule references a missing section or room.',
                ];
                continue;
            }

            // ── room double-booking ───────────────────────────────────────
            $roomKey = $room->id.'-'.$timeslotId;
            $roomSections[$room->id][$section->id] = true;
            if (isset($roomTimeslots[$roomKey])) {
                $errors[] = [
                    'type'        => 'room_double_booking',
                    'section_id'  => $section->id,
                    'room_id'     => $room->id,
                    'timeslot_id' => $timeslotId,
                    'message'     => "Room {$room->room_code} is double booked.",
                ];
            }
            $roomTimeslots[$roomKey] = true;

            // ── section duplicate timeslot ────────────────────────────────
            $sectionKey = $section->id.'-'.$timeslotId;
            if (isset($sectionTimeslots[$sectionKey])) {
                $errors[] = [
                    'type'        => 'section_duplicate_timeslot',
                    'section_id'  => $section->id,
                    'timeslot_id' => $timeslotId,
                    'message'     => "Section {$section->section_name} is scheduled more than once in the same timeslot.",
                ];
            }
            $sectionTimeslots[$sectionKey] = true;
            $sectionSlotCounts[$section->id] = ($sectionSlotCounts[$section->id] ?? 0) + 1;

            // ── room capacity ─────────────────────────────────────────────
            if ($room->capacity < $section->capacity) {
                $errors[] = [
                    'type'       => 'room_capacity',
                    'section_id' => $section->id,
                    'room_id'    => $room->id,
                    'message'    => "Room {$room->room_code} capacity is too small for section {$section->section_name}.",
                ];
            }

            // ── room type suitability ─────────────────────────────────────
            if (!$this->isRoomSuitableForCourse($room, $section->courseOffering->course)) {
                $hasExactRoomMatch = $this->rooms->contains(function ($candidate) use ($section) {
                    return $candidate->capacity >= $section->capacity
                        && $this->isRoomSuitableForCourse($candidate, $section->courseOffering->course);
                });
                if ($hasExactRoomMatch) {
                    $errors[] = [
                        'type'       => 'room_type',
                        'section_id' => $section->id,
                        'room_id'    => $room->id,
                        'message'    => "Room {$room->room_code} is not suitable for {$section->courseOffering->course->course_name}.",
                    ];
                }
            }

            // ── teacher conflicts & workload ──────────────────────────────
            foreach ($section->teachers as $teacher) {
                $teacherKey = $teacher->id.'-'.$timeslotId;
                if (isset($teacherTimeslots[$teacherKey])) {
                    $errors[] = [
                        'type'        => 'teacher_conflict',
                        'section_id'  => $section->id,
                        'teacher_id'  => $teacher->id,
                        'timeslot_id' => $timeslotId,
                        'message'     => "Teacher {$teacher->full_name} has a timeslot conflict.",
                    ];
                }
                $teacherTimeslots[$teacherKey]                = true;
                $teacherSlotCount[$teacher->id]               = ($teacherSlotCount[$teacher->id] ?? 0) + 1;
                $teacherModels[$teacher->id]                  = $teacher;
            }

            // ── student conflicts (optional) ──────────────────────────────
            if ($this->shouldValidateStudentConflicts()) {
                foreach ($this->sectionStudentIds[$section->id] ?? [] as $studentId) {
                    $studentKey = $studentId.'-'.$timeslotId;
                    if (isset($studentTimeslots[$studentKey])) {
                        $errors[] = [
                            'type'        => 'student_conflict',
                            'section_id'  => $section->id,
                            'student_id'  => $studentId,
                            'timeslot_id' => $timeslotId,
                            'message'     => "Student {$studentId} has a timeslot conflict.",
                        ];
                    }
                    $studentTimeslots[$studentKey] = true;
                }
            }
        }

        // ── teacher weekly-hour cap (slot count == hours, 1 : 1) ─────────
        foreach ($teacherSlotCount as $teacherId => $scheduledHours) {
            $teacher  = $teacherModels[$teacherId];
            $maxHours = $this->getTeacherMaxWeeklyHours($teacher);
            if ($scheduledHours > $maxHours) {
                $errors[] = [
                    'type'       => 'teacher_workload',
                    'teacher_id' => $teacherId,
                    'message'    => "Teacher {$teacher->full_name} is assigned {$scheduledHours} weekly hour(s), "
                                  . "above the {$maxHours}-hour limit.",
                ];
            }
        }

        // ── section must have exactly the right number of slots ───────────
        foreach ($sections as $sectionId => $section) {
            $requiredSlots  = $this->getRequiredWeeklyHours($section->courseOffering->course);
            $scheduledSlots = $sectionSlotCounts[$sectionId] ?? 0;
            if ($scheduledSlots !== $requiredSlots) {
                $errors[] = [
                    'type'            => 'section_slot_mismatch',
                    'section_id'      => $sectionId,
                    'required_slots'  => $requiredSlots,
                    'scheduled_slots' => $scheduledSlots,
                    'message'         => "Section {$section->section_name} requires {$requiredSlots} weekly hour(s), "
                                       . "but {$scheduledSlots} were scheduled.",
                ];
            }
        }

        // ── room section-count & combined-hours limits (optional strict sharing) ──
        if (!$this->isStrictRoomSharing()) {
            return $errors;
        }

        foreach ($roomSections as $roomId => $sectionsInRoom) {
            $sectionIds = array_keys($sectionsInRoom);
            $maxSections = (int) config('scheduling.room_max_sections', 0);
            if ($maxSections > 0 && count($sectionIds) > $maxSections) {
                $errors[] = [
                    'type'    => 'room_section_limit',
                    'room_id' => $roomId,
                    'message' => "Room {$roomId} is assigned to more than {$maxSections} sections.",
                ];
                continue;
            }

            $roomLoad = 0;
            foreach ($sectionIds as $sectionId) {
                $section = $sections->get($sectionId);
                if ($section) {
                    $roomLoad += $this->getRequiredWeeklyHours($section->courseOffering->course);
                }
            }

            $roomHoursLimit = (int) config('scheduling.room_combined_hours_limit', 38);
            if ($roomLoad > $roomHoursLimit) {
                $errors[] = [
                    'type'    => 'room_combined_hours',
                    'room_id' => $roomId,
                    'message' => "Room {$roomId} is assigned {$roomLoad} weekly hour(s), "
                               . "above the {$roomHoursLimit}-hour shared-room limit.",
                ];
            }
        }

        return $errors;
    }

    // =========================================================================
    //  TIMESLOT VALIDITY  (used by both engines)
    // =========================================================================

    /**
     * A timeslot is valid for a section when:
     *   1. No teacher is already teaching at that timeslot.
     *   2. Every teacher still has room for 1 more hour this week.
     *   3. No enrolled student is already occupied at that timeslot.
     */
    protected function isTimeslotValid($section, $course, $timeslot, bool $ignoreStudentConflicts = false): bool
    {
        foreach ($section->teachers as $teacher) {
            if (isset($this->teacherTimeslotMap[$teacher->id][$timeslot->id])) {
                return false;
            }
            if (!$this->teacherCanAcceptOneMoreHour($teacher)) {
                return false;
            }
        }

        $checkStudents = !$ignoreStudentConflicts
            && config('scheduling.enforce_student_conflicts', true);

        if ($checkStudents) {
            foreach ($this->sectionStudentIds[$section->id] ?? [] as $studentId) {
                if (isset($this->studentTimeslotMap[$studentId][$timeslot->id])) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function isStrictRoomSharing(): bool
    {
        return (bool) config('scheduling.strict_room_sharing', false);
    }

    protected function shouldValidateStudentConflicts(): bool
    {
        if ($this->completionRelaxedStudents) {
            return false;
        }

        return (bool) config('scheduling.validate_student_conflicts', false);
    }

    protected function filterBlockingValidationErrors(array $errors): array
    {
        return collect($errors)
            ->reject(fn ($error) => ($error['type'] ?? '') === 'student_conflict'
                && !config('scheduling.validate_student_conflicts', false))
            ->values()
            ->all();
    }

    // =========================================================================
    //  ADVANCED SCHEDULING (backtracking + greedy fallback)
    // =========================================================================

    protected function attemptAdvancedScheduling()
    {
        try {
            $sortedSections  = $this->sortSectionsByDifficulty();
            $this->sections  = $sortedSections;
            $this->resetSchedulingState();

            $success = $this->backtrackAssignSections(0, $this->timeslots);

            if ($success) {
                return $this->currentSchedule;
            }

            return $this->attemptGreedyScheduling();

        } catch (\Exception $e) {
            Log::error('Advanced scheduling failed: '.$e->getMessage());
            return [];
        }
    }

    protected function attemptGreedyScheduling()
    {
        $finalSchedule       = [];
        $usedRoomTimeslots   = [];

        foreach ($this->sections as $section) {
            $course        = $section->courseOffering->course;
            $requiredSlots = $this->getRequiredWeeklyHours($course);
            $assignedSlots = 0;

            foreach ($this->timeslots as $timeslot) {
                if ($assignedSlots >= $requiredSlots) {
                    break;
                }

                if (!$this->isTimeslotValid($section, $course, $timeslot)) {
                    continue;
                }

                $room        = null;
                $roomsToTry  = $this->getEligibleRooms($course)
                    ->filter(fn ($r) => $this->canUseRoomForSection($section, $r))
                    ->sortByDesc(fn ($r) => $this->getRoomSelectionScore($section, $r));

                foreach ($roomsToTry as $potentialRoom) {
                    if (!$this->canUseRoomForSection($section, $potentialRoom)) {
                        continue;
                    }
                    if (isset($usedRoomTimeslots[$potentialRoom->id.'_'.$timeslot->id])) {
                        continue;
                    }
                    $room = $potentialRoom;
                    break;
                }

                if (!$room) {
                    continue;
                }

                $finalSchedule[] = [
                    'section_id'  => $section->id,
                    'room_id'     => $room->id,
                    'timeslot_id' => $timeslot->id,
                ];

                $usedRoomTimeslots[$room->id.'_'.$timeslot->id] = true;
                $this->sectionRoomMap[$section->id]             = $room->id;
                $this->roomSectionsMap[$room->id][$section->id] = true;
                $this->roomTimeslotMap[$room->id][$timeslot->id] = true;

                foreach ($section->teachers as $teacher) {
                    $this->teacherTimeslotMap[$teacher->id][$timeslot->id] = true;
                    $this->teacherHours[$teacher->id]                      = ($this->teacherHours[$teacher->id] ?? 0) + 1;
                    $this->teacherScheduledDays[$teacher->id][$timeslot->day_of_week] = true;
                    $this->teacherDayUsage[$teacher->id][$timeslot->day_of_week] =
                        ($this->teacherDayUsage[$teacher->id][$timeslot->day_of_week] ?? 0) + 1;
                }

                foreach ($this->sectionStudentIds[$section->id] ?? [] as $studentId) {
                    $this->studentTimeslotMap[$studentId][$timeslot->id] = true;
                }

                $this->dayUsageCount[$timeslot->day_of_week] =
                    ($this->dayUsageCount[$timeslot->day_of_week] ?? 0) + 1;

                $assignedSlots++;
            }
        }

        return $finalSchedule;
    }

    // =========================================================================
    //  BACKTRACKING
    // =========================================================================

    protected function backtrackAssignSections($sectionIndex, $availableTimeslots): bool
    {
        $this->backtrackCount++;

        if ($this->backtrackCount > $this->maxBacktrackSteps) {
            return false;
        }
        if (microtime(true) - $this->startTime > $this->timeLimitSeconds) {
            return false;
        }
        if ($sectionIndex >= $this->sections->count()) {
            return true;
        }

        $section       = $this->sections[$sectionIndex];
        $course        = $section->courseOffering->course;
        $requiredSlots = $this->getRequiredWeeklyHours($course);

        $possibleAssignments = $this->getPossibleAssignments($section, $course, $availableTimeslots, $requiredSlots);

        if ($possibleAssignments->isEmpty()) {
            return false;
        }

        if ($possibleAssignments->count() > $this->maxCombinationsPerSection) {
            $possibleAssignments = $possibleAssignments->take($this->maxCombinationsPerSection);
        }

        $possibleAssignments = $possibleAssignments->sortByDesc(fn ($a) => $a['score']);

        foreach ($possibleAssignments as $assignment) {
            $this->applyAssignment($section, $assignment);

            $currentSlots = $this->getCurrentSectionSlotCount($section->id);
            $result       = $currentSlots < $requiredSlots
                ? $this->backtrackAssignSections($sectionIndex, $availableTimeslots)
                : $this->backtrackAssignSections($sectionIndex + 1, $availableTimeslots);

            if ($result) {
                return true;
            }

            $this->undoAssignment($section, $assignment);
        }

        return false;
    }

    protected function getPossibleAssignments($section, $course, $availableTimeslots, $requiredSlots)
    {
        $possibilities   = collect();
        $preferredRoomId = $this->sectionRoomMap[$section->id] ?? null;

        $validTimeslots = $availableTimeslots->filter(
            fn ($ts) => $this->isTimeslotValid($section, $course, $ts)
        );

        if ($validTimeslots->isEmpty()) {
            return $possibilities;
        }

        foreach ($validTimeslots as $timeslot) {
            $eligibleRooms = $this->getEligibleRooms($course)
                ->filter(fn ($r) => $this->canUseRoomForSection($section, $r));

            $eligibleRooms = $eligibleRooms->sortByDesc(function ($r) use ($section, $preferredRoomId) {
                $score = $this->getRoomSelectionScore($section, $r);
                if ($preferredRoomId && (int) $r->id === (int) $preferredRoomId) {
                    $score += 50;
                }

                return $score;
            });

            foreach ($eligibleRooms as $room) {
                if ($this->hasRoomConflictInMemory($room, $timeslot)) {
                    continue;
                }

                $score = $this->calculateAssignmentScore($section, $course, [$timeslot], $room);

                $possibilities->push([
                    'timeslots' => collect([$timeslot]),
                    'room'      => $room,
                    'day'       => $timeslot->day_of_week,
                    'score'     => $score,
                ]);
            }
        }

        return $possibilities;
    }

    protected function applyAssignment($section, &$assignment): void
    {
        $timeslots = $assignment['timeslots'];
        $room      = $assignment['room'];
        $day       = $assignment['day'];

        $this->sectionRoomMap[$section->id]             = $room->id;
        $this->roomSectionsMap[$room->id][$section->id] = true;
        $this->dayUsageCount[$day] = ($this->dayUsageCount[$day] ?? 0) + count($timeslots);

        foreach ($timeslots as $timeslot) {
            $this->currentSchedule[] = [
                'section_id'  => $section->id,
                'room_id'     => $room->id,
                'timeslot_id' => $timeslot->id,
            ];

            foreach ($section->teachers as $teacher) {
                $this->teacherTimeslotMap[$teacher->id][$timeslot->id] = true;
                $this->teacherHours[$teacher->id] = ($this->teacherHours[$teacher->id] ?? 0) + 1;
                $this->teacherDayUsage[$teacher->id][$day] =
                    ($this->teacherDayUsage[$teacher->id][$day] ?? 0) + 1;
                $this->teacherScheduledDays[$teacher->id][$day] = true;
            }

            $this->roomTimeslotMap[$room->id][$timeslot->id] = true;

            foreach ($this->sectionStudentIds[$section->id] ?? [] as $studentId) {
                $this->studentTimeslotMap[$studentId][$timeslot->id] = true;
            }
        }

        $this->sectionAssignments[$section->id] = $assignment;
    }

    protected function undoAssignment($section, $assignment): void
    {
        $timeslots = $assignment['timeslots'];
        $room      = $assignment['room'];
        $day       = $assignment['day'];

        $this->dayUsageCount[$day] = max(0, ($this->dayUsageCount[$day] ?? 0) - count($timeslots));

        foreach ($timeslots as $timeslot) {
            array_pop($this->currentSchedule);

            foreach ($section->teachers as $teacher) {
                unset($this->teacherTimeslotMap[$teacher->id][$timeslot->id]);
                $this->teacherHours[$teacher->id] = max(0, ($this->teacherHours[$teacher->id] ?? 0) - 1);
                if (($this->teacherHours[$teacher->id] ?? 0) === 0) {
                    unset($this->teacherHours[$teacher->id]);
                }
                $this->teacherDayUsage[$teacher->id][$day] =
                    max(0, ($this->teacherDayUsage[$teacher->id][$day] ?? 0) - 1);
                if (($this->teacherDayUsage[$teacher->id][$day] ?? 0) === 0) {
                    unset($this->teacherDayUsage[$teacher->id][$day]);
                }
                if (!$this->teacherHasAnyScheduledTimeslotOnDay($teacher->id, $day)) {
                    unset($this->teacherScheduledDays[$teacher->id][$day]);
                }
            }

            unset($this->roomTimeslotMap[$room->id][$timeslot->id]);

            foreach ($this->sectionStudentIds[$section->id] ?? [] as $studentId) {
                unset($this->studentTimeslotMap[$studentId][$timeslot->id]);
            }
        }

        if ($this->getCurrentSectionSlotCount($section->id) === 0) {
            unset($this->sectionRoomMap[$section->id]);
            unset($this->roomSectionsMap[$room->id][$section->id]);
            if (empty($this->roomSectionsMap[$room->id])) {
                unset($this->roomSectionsMap[$room->id]);
            }
        }

        unset($this->sectionAssignments[$section->id]);
    }

    protected function getCurrentSectionSlotCount($sectionId): int
    {
        $count = 0;
        foreach ($this->currentSchedule as $a) {
            if ($a['section_id'] == $sectionId) {
                $count++;
            }
        }
        return $count;
    }

    // =========================================================================
    //  SCORING
    // =========================================================================

    protected function calculateAssignmentScore($section, $course, $timeslots, $room): int
    {
        $score = 100;
        $day   = $timeslots[0]->day_of_week;

        // Prefer less-used days
        $score -= ($this->dayUsageCount[$day] ?? 0) * 5;

        $primaryTeacher = $section->teachers->first();
        if ($primaryTeacher) {
            $current  = $this->teacherHours[$primaryTeacher->id] ?? 0;
            $cap      = $this->getTeacherMaxWeeklyHours($primaryTeacher);
            $score   += max(0, $cap - $current) * 10;
            $score   -= ($this->teacherDayUsage[$primaryTeacher->id][$day] ?? 0) * 3;

            if ($this->isConsecutiveTeachingDay($primaryTeacher, $day)) {
                $score -= 25;
            }
            foreach ($timeslots as $timeslot) {
                if ($this->isConsecutiveTeachingPeriod($primaryTeacher, $timeslot)) {
                    $score -= 25;
                    break;
                }
            }
        }

        foreach ($timeslots as $timeslot) {
            $hour = (int) substr($timeslot->start_time, 0, 2);
            if ($hour >= 9 && $hour <= 16) {
                $score += 10;
            } elseif ($hour < 8 || $hour > 18) {
                $score -= 5;
            }
        }

        $preferredRoomType = $this->getRequiredRoomType($course);
        if ($preferredRoomType === 'any' || $this->normalizeRoomType($room->type ?? 'lecture') === $preferredRoomType) {
            $score += 20;
        }

        $score += $this->getRoomSelectionScore($section, $room);
        $score += $this->weights['day_distribution'];

        return $score;
    }

    // =========================================================================
    //  ROOM HELPERS
    // =========================================================================

    protected function canUseRoomForSection($section, $room): bool
    {
        if ($room->capacity < $section->capacity) {
            return false;
        }

        if (!$this->isStrictRoomSharing()) {
            return true;
        }

        $roomSectionIds = array_keys($this->roomSectionsMap[$room->id] ?? []);

        if (in_array($section->id, $roomSectionIds, true)) {
            return true;
        }

        $maxSections = (int) config('scheduling.room_max_sections', 0);
        if ($maxSections > 0 && count($roomSectionIds) >= $maxSections) {
            return false;
        }

        if (empty($roomSectionIds)) {
            return true;
        }

        $existingSection = $this->sections->firstWhere('id', $roomSectionIds[0]);
        if (!$existingSection) {
            return false;
        }

        if (!$this->isSectionRoomPairCompatible($section, $existingSection)) {
            return false;
        }

        $combinedHours = $this->roomWeeklyLoad($room->id)
            + $this->getRequiredWeeklyHours($section->courseOffering->course);

        return $combinedHours <= (int) config('scheduling.room_combined_hours_limit', 38);
    }

    protected function getRoomSelectionScore($section, $room): int
    {
        $roomSectionIds = array_keys($this->roomSectionsMap[$room->id] ?? []);

        if (in_array($section->id, $roomSectionIds, true)) {
            return 1000;
        }

        if (!$this->isStrictRoomSharing()) {
            return empty($roomSectionIds) ? 800 : 600;
        }

        if (empty($roomSectionIds)) {
            return 800;
        }
        $maxSections = (int) config('scheduling.room_max_sections', 0);
        if ($maxSections > 0 && count($roomSectionIds) >= $maxSections) {
            return -1000;
        }

        $existingSection = $this->sections->firstWhere('id', $roomSectionIds[0]);
        if (!$existingSection) {
            return -1000;
        }

        $deptA  = $this->getSectionDepartmentId($section);
        $deptB  = $this->getSectionDepartmentId($existingSection);
        $batchA = $this->getSectionBatchKey($section);
        $batchB = $this->getSectionBatchKey($existingSection);

        if ($deptA === $deptB && $batchA === $batchB) {
            return 900;
        }
        if ($deptA === $deptB && $this->isAdjacentBatch($batchA, $batchB)) {
            return 800;
        }
        if ($deptA !== $deptB && $batchA === $batchB) {
            return 700;
        }

        return -1000;
    }

    protected function roomWeeklyLoad(int $roomId): int
    {
        $sectionIds = array_keys($this->roomSectionsMap[$roomId] ?? []);
        return collect($sectionIds)->sum(function ($sectionId) {
            $section = $this->sections->firstWhere('id', $sectionId);
            return $section ? $this->getRequiredWeeklyHours($section->courseOffering->course) : 0;
        });
    }

    protected function findAvailableRoom($section, $course, $timeslot)
    {
        return $this->getEligibleRooms($course)
            ->filter(fn ($r) => $this->canUseRoomForSection($section, $r))
            ->sortByDesc(fn ($r) => $this->getRoomSelectionScore($section, $r))
            ->first(fn ($r) => !$this->hasRoomConflictInMemory($r, $timeslot));
    }

    protected function hasRoomConflictInMemory($room, $timeslot): bool
    {
        return isset($this->roomTimeslotMap[$room->id][$timeslot->id]);
    }

    protected function getEligibleRooms($course)
    {
        $eligible = $this->rooms->filter(fn ($r) => $this->isRoomSuitableForCourse($r, $course))->values();

        if ($eligible->isNotEmpty()) {
            return $eligible;
        }

        $fallback = $this->rooms->values();
        $courseId = $course->id ?? null;
        if (!isset($this->warnedRoomFallbackCourses[$courseId])) {
            Log::warning('No exact room-type match for course; using capacity-based fallback.', [
                'course_id'          => $courseId,
                'course_name'        => $course->course_name ?? null,
                'required_room_type' => $this->getRequiredRoomType($course),
            ]);
            $this->warnedRoomFallbackCourses[$courseId] = true;
        }

        return $fallback;
    }

    // =========================================================================
    //  ROOM TYPE HELPERS
    // =========================================================================

    protected function getRequiredRoomType($course): string
    {
        $required    = strtolower(trim((string) ($course->required_room_type ?? '')));
        $courseName  = strtolower(trim((string) ($course->course_name ?? '')));
        $courseLevel = strtolower(trim((string) ($course->level ?? 'undergraduate')));

        if ($required !== '' && $required !== 'any') {
            return $this->normalizeRoomType($required);
        }
        if (str_contains($courseName, 'lab')) {
            return 'lab';
        }
        if ($courseLevel === 'graduate') {
            return 'seminar';
        }
        return 'lecture';
    }

    protected function normalizeRoomType($roomType): string
    {
        return match (strtolower(trim((string) $roomType))) {
            'laboratory', 'computer lab', 'computer-lab', 'computer_lab' => 'lab',
            'classroom', 'hall', 'auditorium'                            => 'lecture',
            default => strtolower(trim((string) $roomType)),
        };
    }

    protected function isRoomSuitableForCourse($room, $course): bool
    {
        $roomType     = $this->normalizeRoomType($room->type ?? 'lecture');
        $requiredType = $this->getRequiredRoomType($course);

        if ($requiredType === 'any') {
            return true;
        }
        if ($requiredType === 'seminar') {
            return in_array($roomType, ['seminar', 'conference', 'lecture'], true);
        }
        if ($requiredType === 'lecture') {
            return in_array($roomType, ['lecture', 'seminar', 'conference'], true);
        }
        return $roomType === $requiredType;
    }

    // =========================================================================
    //  TEACHER DAY / PERIOD HELPERS
    // =========================================================================

    protected function getDayIndex(string $day): int
    {
        return (int) (config("scheduling.days.$day") ?? 99);
    }

    protected function isConsecutiveTeachingDay($teacher, string $day): bool
    {
        $candidateIndex = $this->getDayIndex($day);
        foreach (array_keys($this->teacherScheduledDays[$teacher->id] ?? []) as $existingDay) {
            if (abs($candidateIndex - $this->getDayIndex($existingDay)) === 1) {
                return true;
            }
        }
        return false;
    }

    protected function isConsecutiveTeachingPeriod($teacher, $timeslot): bool
    {
        $candidate = $this->timeslotOrderMap[$timeslot->id] ?? null;
        if (!$candidate) {
            return false;
        }
        foreach (array_keys($this->teacherTimeslotMap[$teacher->id] ?? []) as $existingTimeslotId) {
            $existing = $this->timeslotOrderMap[$existingTimeslotId] ?? null;
            if ($existing
                && $candidate['day'] === $existing['day']
                && abs($candidate['index'] - $existing['index']) === 1
            ) {
                return true;
            }
        }
        return false;
    }

    protected function teacherHasAnyScheduledTimeslotOnDay(int $teacherId, string $day): bool
    {
        foreach (array_keys($this->teacherTimeslotMap[$teacherId] ?? []) as $timeslotId) {
            $slot = $this->timeslotOrderMap[$timeslotId] ?? null;
            if ($slot && $slot['day'] === $day) {
                return true;
            }
        }
        return false;
    }

    // =========================================================================
    //  SECTION / BATCH HELPERS
    // =========================================================================

    protected function getSectionDepartmentId($section): ?int
    {
        $deptId = $section->courseOffering?->course?->department_id;
        return $deptId ? (int) $deptId : null;
    }

    protected function getSectionBatchKey($section): string
    {
        return (string) ($section->courseOffering?->semester_id ?? '');
    }

    protected function isAdjacentBatch($batchA, $batchB): bool
    {
        if (!is_numeric($batchA) || !is_numeric($batchB)) {
            return false;
        }
        return abs((int) $batchA - (int) $batchB) === 1;
    }

    protected function isSectionRoomPairCompatible($sectionA, $sectionB): bool
    {
        $deptA  = $this->getSectionDepartmentId($sectionA);
        $deptB  = $this->getSectionDepartmentId($sectionB);
        $batchA = $this->getSectionBatchKey($sectionA);
        $batchB = $this->getSectionBatchKey($sectionB);

        if ($deptA === $deptB && $batchA === $batchB) return true;
        if ($deptA === $deptB && $this->isAdjacentBatch($batchA, $batchB)) return true;
        if ($deptA !== $deptB && $batchA === $batchB) return true;

        return false;
    }

    // =========================================================================
    //  DATA LOADING & VALIDATION
    // =========================================================================

    protected function loadData(): void
    {
        $this->sections = Section::with([
            'courseOffering.course',
            'courseOffering.semester',
            'teachers',
            'enrollments',
        ])
            ->whereHas('courseOffering', fn ($q) => $q->where('semester_id', $this->semesterId))
            ->get()
            ->filter(fn ($s) => $s->courseOffering && $s->courseOffering->course)
            ->values();

        $this->totalSectionCount = $this->sections->count();

        $this->teachers = Teacher::all();
        $this->rooms    = Room::all();

        $this->timeslots = Timeslot::whereIn('day_of_week', [
            'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday',
        ])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        if ($this->timeslots->isEmpty()) {
            $this->timeslots = $this->createDefaultTimeslots();
        }

        $this->timeslotsByDay = $this->timeslots->groupBy('day_of_week');
        $this->resetSchedulingState();

        // Feasibility pre-check
        $totalRequired = $this->sections->sum(
            fn ($s) => $this->getRequiredWeeklyHours($s->courseOffering->course)
        );
        $totalCapacity = $this->timeslots->count() * $this->rooms->count();

        if ($totalRequired > $totalCapacity) {
            throw new \Exception(
                "Insufficient total capacity. Required: {$totalRequired}, available: {$totalCapacity}."
            );
        }

        foreach ($this->sections as $section) {
            if ($section->teachers->count() === 0) {
                throw new \Exception(
                    "Cannot schedule section {$section->section_name}: no teacher assigned."
                );
            }
        }
    }

    protected function validateData(): void
    {
        if ($this->sections->isEmpty()) {
            throw new \Exception('No sections found for this semester.');
        }
        if ($this->teachers->isEmpty()) {
            throw new \Exception('No teachers found in the system.');
        }
        if ($this->rooms->isEmpty()) {
            throw new \Exception('No rooms found in the system.');
        }
        if ($this->timeslots->isEmpty()) {
            throw new \Exception('No timeslots found in the system.');
        }

        foreach ($this->sections as $section) {
            if ($section->teachers->isEmpty()) {
                throw new \Exception(
                    "Cannot schedule section {$section->section_name}: no teacher assigned."
                );
            }

            $hasRoom = $this->rooms->contains(fn ($r) => $r->capacity >= $section->capacity);
            if (!$hasRoom) {
                throw new \Exception(
                    "Cannot schedule section {$section->section_name}: no room large enough for "
                    . "{$section->courseOffering->course->course_name}."
                );
            }
        }
    }

    protected function precomputeData(): void
    {
        foreach ($this->sections as $section) {
            $this->sectionStudentIds[$section->id] = $section->enrollments->pluck('student_id')->toArray();
        }

        $this->timeslotsByDay  = $this->timeslots->groupBy('day_of_week');
        $this->timeslotOrderMap = [];

        foreach ($this->timeslotsByDay as $day => $dayTimeslots) {
            foreach ($dayTimeslots->sortBy('start_time')->values() as $index => $timeslot) {
                $this->timeslotOrderMap[$timeslot->id] = ['day' => $day, 'index' => $index];
            }
        }

        foreach ($this->teachers as $teacher) {
            $this->teacherCourseMap[$teacher->id] = [];
        }
        foreach ($this->rooms as $room) {
            $this->roomCache[$room->id] = [];
        }
    }

    protected function sortSectionsByDifficulty()
    {
        return $this->sections->sortByDesc(function ($section) {
            $difficulty  = count($this->sectionStudentIds[$section->id] ?? []) * 10;
            $difficulty += (10 - min(10, $section->teachers->count())) * 5;
            $difficulty += $this->getRequiredWeeklyHours($section->courseOffering->course) * 2;

            $eligibleRooms = $this->getEligibleRooms($section->courseOffering->course)->count();
            if ($eligibleRooms === 0) {
                $difficulty += 100;
            } elseif ($eligibleRooms < 3) {
                $difficulty += 20;
            }

            return $difficulty;
        })->values();
    }

    // =========================================================================
    //  COMPLETION PASS (fill remaining weekly slots)
    // =========================================================================

    protected function attemptFillMissingSlots(array $assignments, $requiredSlotsBySection): array
    {
        $this->loadScheduleIntoState($assignments);

        foreach ($requiredSlotsBySection as $sectionId => $required) {
            $section = $this->sections->firstWhere('id', $sectionId);
            if (!$section) {
                continue;
            }

            $course = $section->courseOffering->course;

            while ($this->getCurrentSectionSlotCount($sectionId) < $required) {
                $placed = $this->placeOneSlotForSection($section, $course, false);

                if (!$placed && config('scheduling.relax_student_conflicts_on_completion', true)) {
                    $placed = $this->placeOneSlotForSection($section, $course, true);
                    if ($placed) {
                        $this->completionRelaxedStudents = true;
                    }
                }

                if (!$placed) {
                    Log::warning('Completion pass could not place slot', [
                        'section_id'   => $sectionId,
                        'section_name' => $section->section_name,
                        'scheduled'    => $this->getCurrentSectionSlotCount($sectionId),
                        'required'     => $required,
                    ]);
                    break;
                }
            }
        }

        return $this->filterSemesterSchedule($this->currentSchedule);
    }

    protected function placeOneSlotForSection($section, $course, bool $ignoreStudentConflicts): bool
    {
        $eligibleRooms = $this->getEligibleRooms($course)
            ->filter(fn ($r) => $this->canUseRoomForSection($section, $r))
            ->sortByDesc(fn ($r) => $this->getRoomSelectionScore($section, $r));

        foreach ($this->timeslots as $timeslot) {
            if (!$this->isTimeslotValid($section, $course, $timeslot, $ignoreStudentConflicts)) {
                continue;
            }

            foreach ($eligibleRooms as $room) {
                if ($this->hasRoomConflictInMemory($room, $timeslot)) {
                    continue;
                }

                $assignment = [
                    'timeslots' => collect([$timeslot]),
                    'room'      => $room,
                    'day'       => $timeslot->day_of_week,
                    'score'     => 0,
                ];
                $this->applyAssignment($section, $assignment);

                return true;
            }
        }

        return false;
    }

    protected function loadScheduleIntoState(array $assignments): void
    {
        $this->resetSchedulingState();

        foreach ($this->filterSemesterSchedule($assignments) as $entry) {
            $section = $this->sections->firstWhere('id', $entry['section_id']);
            $timeslot = $this->timeslots->firstWhere('id', $entry['timeslot_id']);
            $room = $this->rooms->firstWhere('id', $entry['room_id']);

            if (!$section || !$timeslot || !$room) {
                continue;
            }

            $assignment = [
                'timeslots' => collect([$timeslot]),
                'room'      => $room,
                'day'       => $timeslot->day_of_week,
                'score'     => 0,
            ];
            $this->applyAssignment($section, $assignment);
        }
    }

    // =========================================================================
    //  STATE MANAGEMENT
    // =========================================================================

    protected function resetSchedulingState(): void
    {
        $this->teacherTimeslotMap          = [];
        $this->roomTimeslotMap             = [];
        $this->studentTimeslotMap          = [];
        $this->teacherHours                = [];
        $this->sectionAssignments          = [];
        $this->sectionRoomMap              = [];
        $this->roomSectionsMap             = [];
        $this->teacherScheduledDays        = [];
        $this->warnedRoomFallbackCourses   = [];
        $this->completionRelaxedStudents   = false;
        $this->currentSchedule             = [];
        $this->dayUsageCount               = [];
        $this->teacherDayUsage             = [];
        $this->roomCache                   = [];
        $this->unscheduledSections         = 0;
        $this->conflictCount               = 0;
        $this->conflictDetails             = [];
    }

    // =========================================================================
    //  RESULT HELPERS
    // =========================================================================

    protected function filterSemesterSchedule(array $assignments): array
    {
        $semesterSectionIds = $this->sections->pluck('id')->flip();

        return collect($assignments)
            ->filter(fn ($a) => isset($a['section_id'], $a['room_id'], $a['timeslot_id'])
                && $semesterSectionIds->has($a['section_id']))
            ->map(fn ($a) => [
                'section_id'  => (int) $a['section_id'],
                'room_id'     => (int) $a['room_id'],
                'timeslot_id' => (int) $a['timeslot_id'],
            ])
            ->unique(fn ($a) => $a['section_id'].'-'.$a['timeslot_id'])
            ->values()
            ->all();
    }

    protected function normalizeScheduleEntries(array $assignments): array
    {
        return collect($assignments)
            ->map(fn ($a) => [
                'section_id'  => (int) $a['section_id'],
                'room_id'     => (int) $a['room_id'],
                'timeslot_id' => (int) $a['timeslot_id'],
            ])
            ->values()
            ->all();
    }

    protected function formatIncompleteScheduleMessage(array $coverage): string
    {
        $missingCount = count($coverage['missing']);
        $base         = "Unable to generate a complete schedule. {$coverage['scheduled_slots']} of "
             . "{$coverage['required_slots']} required weekly slots were scheduled; "
             . "{$missingCount} section(s) are incomplete.";

        $details = collect($coverage['missing'] ?? [])
            ->take(3)
            ->map(fn ($m) => ($m['section_name'] ?? 'Section').': '
                .($m['scheduled_slots'] ?? 0).'/'.($m['required_slots'] ?? '?').' slots')
            ->implode('; ');

        if ($details !== '') {
            $base .= ' ('.$details.')';
        }

        return $base;
    }

    protected function getDayDistributionStats(): array
    {
        $stats = [];
        foreach ($this->dayUsageCount as $day => $count) {
            $stats[$day] = $count;
        }
        return $stats;
    }

    protected function getCurrentScore(): int
    {
        $score = 0;
        foreach ($this->teacherHours as $hours) {
            $score += max(0, config('scheduling.max_teacher_hours_per_week', 38) - $hours) * 10;
        }
        foreach ($this->roomTimeslotMap as $roomTimeslots) {
            $score += count($roomTimeslots) * 5;
        }
        $avgUsage = array_sum($this->dayUsageCount) / max(1, count($this->dayUsageCount));
        foreach ($this->dayUsageCount as $usage) {
            $score -= abs($usage - $avgUsage) * 2;
        }
        $score -= ($this->sections->count() - count($this->sectionAssignments)) * 100;
        return $score;
    }

    protected function prepareResult($success): array
    {
        $executionTime    = microtime(true) - $this->startTime;
        $totalRequired    = $this->sections->sum(fn ($s) => $this->getRequiredWeeklyHours($s->courseOffering->course));
        $scheduledCount   = count($this->bestSchedule ?? $this->currentSchedule);
        $scheduledSectionCount = count($this->sectionAssignments);
        $conflicts        = max(0, $this->totalSectionCount - $scheduledSectionCount);

        return [
            'success'           => ($scheduledCount > 0),
            'perfect'           => ($conflicts === 0),
            'message'           => ($conflicts === 0 ? 'Schedule generated successfully' : 'Partial schedule generated with conflicts'),
            'scheduled'         => $scheduledCount,
            'total_sections'    => $this->totalSectionCount,
            'scheduled_sections'=> $scheduledSectionCount,
            'conflicts'         => $conflicts,
            'day_distribution'  => $this->getDayDistributionStats(),
            'total_required'    => $totalRequired,
            'completion_rate'   => round(($scheduledCount / max(1, $totalRequired)) * 100, 2),
            'backtrack_steps'   => $this->backtrackCount,
            'execution_time'    => round($executionTime, 2),
            'best_score'        => $this->bestScore,
            'attempts'          => $this->maxAttempts,
        ];
    }

    // =========================================================================
    //  FORWARD CHECKING (used optionally during backtracking)
    // =========================================================================

    protected function forwardCheck($sectionIndex, $assignment): bool
    {
        $remainingSections = $this->sections->slice($sectionIndex + 1);

        foreach ($remainingSections as $remainingSection) {
            $course        = $remainingSection->courseOffering->course;
            $requiredSlots = $this->getRequiredWeeklyHours($course);

            $validTimeslots = $this->timeslots->filter(
                fn ($ts) => $this->isTimeslotValid($remainingSection, $course, $ts)
                    && $this->findAvailableRoom($remainingSection, $course, $ts) !== null
            );

            if ($validTimeslots->count() < $requiredSlots) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    //  DEFAULT TIMESLOTS (fallback)
    // =========================================================================

    protected function createDefaultTimeslots()
    {
        $slots   = collect();
        $days    = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $times   = [
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
                $slots->push((object) [
                    'id'          => $counter++,
                    'day_of_week' => $day,
                    'start_time'  => $time['start'],
                    'end_time'    => $time['end'],
                ]);
            }
        }

        return $slots;
    }
}
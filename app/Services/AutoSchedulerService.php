<?php

namespace App\Services;

use App\Support\TimeslotDuration;
use App\Support\StudentScheduleRules;
use App\Models\Course;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
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
     * Per-teacher accumulated teaching hours (sum of timeslot durations).
     */
    protected $teacherHours = [];

    protected $sectionAssignments = [];
    protected $sectionRoomMap     = [];
    protected $roomSectionsMap    = [];

    /** Home classroom per student academic section (cohort). */
    protected $cohortRoomMap = [];

    /** Academic cohorts assigned to each physical room. */
    protected $roomCohortsMap = [];

    /** Total weekly teaching hours required per academic cohort. */
    protected $cohortWeeklyHours = [];

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
    protected $sectionStudentTypes        = [];
    protected $studentTypeByStudent       = [];

    /** Dominant student cohort (academic_section) per course section, for room-sharing rules. */
    protected $sectionCohortKeys = [];

    protected $totalSectionCount = 0;

    // Best solution tracking
    protected $bestSchedule        = null;
    protected $bestScore           = -INF;
    protected $currentSchedule     = [];
    protected $bestPartialSchedule = [];
    protected $bestPartialScore    = -INF;

    // Performance & configuration
    protected $maxBacktrackSteps         = 25000;
    protected $backtrackCount            = 0;
    protected $timeLimitSeconds          = 60;
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

            $this->loadData();
            $this->validateData();
            $this->precomputeData();

            $requiredSlotsBySection = $this->getRequiredSlotsBySection();

            $generation = $this->runConstrainedScheduler($requiredSlotsBySection);
            $finalSchedule = $generation['schedule'];
            $coverage      = $generation['coverage'];
            $engineUsed    = $generation['engine'];

            if (!$coverage['complete']) {
                $this->conflictDetails = $coverage['missing'];
                throw new \Exception($this->formatIncompleteScheduleMessage($coverage));
            }

            $validationErrors = $this->validateGeneratedSchedule($finalSchedule);
            if (!empty($validationErrors)) {
                $this->conflictDetails = $validationErrors;
                throw new \Exception('Generated schedule has conflicts: '.$validationErrors[0]['message']);
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

    /**
     * Build a complete schedule that satisfies all hard constraints (multiple attempts).
     */
    protected function runConstrainedScheduler($requiredSlotsBySection): array
    {
        $maxAttempts  = (int) config('scheduling.max_generation_attempts', 5);
        $bestSchedule = [];
        $bestSlots    = -1;
        $engineUsed   = 'advanced';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $this->backtrackCount = 0;
            $this->resetSchedulingState();

            $this->sections = $attempt === 1
                ? $this->sortSectionsByDifficulty()
                : $this->sortSectionsByDifficulty()->shuffle()->values();

            $schedule = [];
            if ($this->backtrackAssignSections(0, $this->timeslots)) {
                $schedule   = $this->currentSchedule;
                $engineUsed = $attempt === 1 ? 'advanced' : 'advanced-retry-'.$attempt;
            } else {
                $this->resetSchedulingState();
                $schedule   = $this->attemptGreedyScheduling();
                $engineUsed = $attempt === 1 ? 'greedy' : 'greedy-retry-'.$attempt;
            }

            $coverage = $this->analyzeScheduleCoverage($schedule, $requiredSlotsBySection);
            if ($coverage['scheduled_slots'] > $bestSlots) {
                $bestSlots    = $coverage['scheduled_slots'];
                $bestSchedule = $schedule;
            }

            if ($coverage['complete'] && empty($this->validateGeneratedSchedule($schedule))) {
                return [
                    'schedule' => $schedule,
                    'coverage' => $coverage,
                    'engine'   => $engineUsed,
                ];
            }
        }

        if (!empty($bestSchedule)) {
            $filled   = $this->attemptFillMissingSlots($bestSchedule, $requiredSlotsBySection);
            $coverage = $this->analyzeScheduleCoverage($filled, $requiredSlotsBySection);

            if ($coverage['complete'] && empty($this->validateGeneratedSchedule($filled))) {
                return [
                    'schedule' => $filled,
                    'coverage' => $coverage,
                    'engine'   => $engineUsed.'+fill',
                ];
            }

            if ($coverage['scheduled_slots'] >= $bestSlots) {
                $bestSchedule = $filled;
                $bestSlots    = $coverage['scheduled_slots'];
            }
        }

        return [
            'schedule' => $bestSchedule,
            'coverage' => $this->analyzeScheduleCoverage($bestSchedule, $requiredSlotsBySection),
            'engine'   => $engineUsed,
        ];
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
     * Can this teacher accept another timeslot without exceeding the weekly cap?
     */
    protected function teacherCanAcceptTimeslot($teacher, $timeslot): bool
    {
        $current = (float) ($this->teacherHours[$teacher->id] ?? 0);
        $add     = TimeslotDuration::teachingHours($timeslot);

        return ($current + $add) <= $this->getTeacherMaxWeeklyHours($teacher);
    }

    protected function getTimeslotTeachingHours($timeslot): float
    {
        return TimeslotDuration::teachingHours($timeslot);
    }

    protected function getCurrentSectionScheduledHours(int $sectionId): float
    {
        $hours = 0.0;
        foreach ($this->currentSchedule as $assignment) {
            if ((int) $assignment['section_id'] !== $sectionId) {
                continue;
            }
            $timeslot = $this->timeslots->firstWhere('id', $assignment['timeslot_id']);
            if ($timeslot) {
                $hours += $this->getTimeslotTeachingHours($timeslot);
            }
        }

        return round($hours, 4);
    }

    protected function sectionHasRequiredHours(int $sectionId, int $requiredHours): bool
    {
        return $this->getCurrentSectionScheduledHours($sectionId) >= ($requiredHours - 0.001);
    }

    protected function sumAssignmentTeachingHours(array $assignments, ?int $sectionId = null): float
    {
        $hours = 0.0;
        foreach ($assignments as $assignment) {
            if ($sectionId !== null && (int) $assignment['section_id'] !== $sectionId) {
                continue;
            }
            $timeslot = $this->timeslots->firstWhere('id', $assignment['timeslot_id']);
            if ($timeslot) {
                $hours += $this->getTimeslotTeachingHours($timeslot);
            }
        }

        return round($hours, 4);
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
        $missing = [];

        foreach ($requiredSlotsBySection as $sectionId => $requiredHours) {
            $scheduledHours = $this->sumAssignmentTeachingHours($assignments, (int) $sectionId);
            if ($scheduledHours + 0.001 < $requiredHours) {
                $section   = $this->sections->firstWhere('id', $sectionId);
                $missing[] = [
                    'type'             => 'insufficient_section_slots',
                    'section_id'       => $sectionId,
                    'section_name'     => $section?->section_name,
                    'course'           => $section?->courseOffering?->course?->course_name,
                    'required_slots'   => $requiredHours,
                    'scheduled_slots'  => $scheduledHours,
                    'message'          => "Section {$section?->section_name} needs {$requiredHours} weekly teaching hour(s), "
                                        . "but only {$scheduledHours} were scheduled.",
                ];
            }
        }

        return [
            'complete'        => empty($missing),
            'missing'         => $missing,
            'required_slots'  => (int) collect($requiredSlotsBySection)->sum(),
            'scheduled_slots' => (int) round($this->sumAssignmentTeachingHours($assignments)),
        ];
    }

    /**
     * Validate the fully-assembled schedule for hard-constraint violations.
     *
     * Teacher workload uses the sum of actual timeslot durations (not slot count).
     */
    protected function validateGeneratedSchedule(array $assignments): array
    {
        $errors           = [];
        $roomTimeslots    = [];
        $sectionTimeslots = [];
        $roomSections     = [];
        $sectionScheduledHours = [];
        $teacherTimeslots = [];
        $teacherHours     = [];
        $teacherModels    = [];
        $studentTimeslots = [];
        $sectionRoomIds   = [];

        $sections = $this->sections->keyBy('id');
        $rooms    = $this->rooms->keyBy('id');
        $timeslotModels = $this->timeslots->keyBy('id');

        foreach ($assignments as $assignment) {
            $section    = $sections->get($assignment['section_id']);
            $room       = $rooms->get($assignment['room_id']);
            $timeslotId = (int) $assignment['timeslot_id'];
            $timeslot   = $timeslotModels->get($timeslotId);

            if (!$section || !$room || !$timeslot) {
                $errors[] = [
                    'type'    => 'invalid_assignment_reference',
                    'message' => 'Generated schedule references a missing section, room, or timeslot.',
                ];
                continue;
            }

            $slotHours = $this->getTimeslotTeachingHours($timeslot);

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
            $sectionScheduledHours[$section->id] = ($sectionScheduledHours[$section->id] ?? 0) + $slotHours;
            $sectionRoomIds[$section->id][$room->id] = true;

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
                $teacherTimeslots[$teacherKey] = true;
                $teacherHours[$teacher->id]    = ($teacherHours[$teacher->id] ?? 0) + $slotHours;
                $teacherModels[$teacher->id]   = $teacher;
            }

            // ── student conflicts ─────────────────────────────────────────
            if (config('scheduling.enforce_student_conflicts', true)) {
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
        foreach ($teacherHours as $teacherId => $scheduledHours) {
            $teacher  = $teacherModels[$teacherId];
            $maxHours = $this->getTeacherMaxWeeklyHours($teacher);
            if ($scheduledHours > $maxHours + 0.001) {
                $errors[] = [
                    'type'       => 'teacher_workload',
                    'teacher_id' => $teacherId,
                    'message'    => "Teacher {$teacher->full_name} is assigned ".round($scheduledHours, 2)." weekly hour(s), "
                                  . "above the {$maxHours}-hour limit.",
                ];
            }
        }

        foreach ($sections as $sectionId => $section) {
            $requiredHours  = $this->getRequiredWeeklyHours($section->courseOffering->course);
            $scheduledHours = $sectionScheduledHours[$sectionId] ?? 0;
            if ($scheduledHours + 0.001 < $requiredHours) {
                $errors[] = [
                    'type'            => 'section_slot_mismatch',
                    'section_id'      => $sectionId,
                    'required_slots'  => $requiredHours,
                    'scheduled_slots' => $scheduledHours,
                    'message'         => "Section {$section->section_name} requires {$requiredHours} weekly teaching hour(s), "
                                       . "but ".round($scheduledHours, 2).' were scheduled.',
                ];
            }

            $roomsUsed = array_keys($sectionRoomIds[$sectionId] ?? []);
            if (count($roomsUsed) > 1) {
                $errors[] = [
                    'type'       => 'section_multiple_rooms',
                    'section_id' => $sectionId,
                    'message'    => "Section {$section->section_name} must use a single classroom for all weekly hours.",
                ];
            }
        }

        // ── room: academic-section (cohort) limits ────────────────────────
        $roomCohorts = [];
        foreach ($assignments as $assignment) {
            $section = $sections->get($assignment['section_id']);
            if (!$section) {
                continue;
            }
            $cohort = $this->getSectionAcademicCohort($section);
            if ($cohort !== '') {
                $roomCohorts[$assignment['room_id']][$cohort] = true;
            }
        }

        foreach ($roomCohorts as $roomId => $cohortsInRoom) {
            $cohortKeys = array_keys($cohortsInRoom);
            $maxCohorts = (int) config('scheduling.room_max_sections', 2);
            if (count($cohortKeys) > $maxCohorts) {
                $errors[] = [
                    'type'    => 'room_section_limit',
                    'room_id' => $roomId,
                    'message' => "Room {$roomId} is assigned to more than {$maxCohorts} academic section(s).",
                ];
            }

            for ($i = 0; $i < count($cohortKeys); $i++) {
                for ($j = $i + 1; $j < count($cohortKeys); $j++) {
                    if (!$this->isAcademicCohortPairCompatible($cohortKeys[$i], $cohortKeys[$j])) {
                        $errors[] = [
                            'type'    => 'room_sharing_incompatible',
                            'room_id' => $roomId,
                            'message' => "Room {$roomId} is shared by incompatible academic sections "
                                       . "{$cohortKeys[$i]} and {$cohortKeys[$j]}.",
                        ];
                    }
                }
            }

            $roomLoad = 0;
            foreach ($cohortKeys as $cohort) {
                $roomLoad += $this->cohortWeeklyHours[$cohort]
                    ?? $this->calculateCohortWeeklyHours($cohort);
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

        // ── each academic cohort must use exactly one classroom ───────────
        $cohortRooms = [];
        foreach ($assignments as $assignment) {
            $section = $sections->get($assignment['section_id']);
            if (!$section) {
                continue;
            }
            $cohort = $this->getSectionAcademicCohort($section);
            if ($cohort === '') {
                continue;
            }
            $cohortRooms[$cohort][$assignment['room_id']] = true;
        }

        foreach ($cohortRooms as $cohort => $roomsUsed) {
            if (count($roomsUsed) > 1) {
                $errors[] = [
                    'type'    => 'cohort_multiple_rooms',
                    'message' => "Academic section {$cohort} must use a single classroom for all courses.",
                ];
            }
        }

        return $errors;
    }

    protected function calculateCohortWeeklyHours(string $cohort): int
    {
        return (int) $this->sections
            ->filter(fn ($s) => $this->getSectionAcademicCohort($s) === $cohort)
            ->sum(fn ($s) => $this->getRequiredWeeklyHours($s->courseOffering->course));
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
    protected function isTimeslotValid($section, $course, $timeslot): bool
    {
        if ($this->sectionHasRequiredHours($section->id, $this->getRequiredWeeklyHours($course))) {
            return false;
        }

        if (!$this->isTimeslotAllowedForSection($section, $timeslot)) {
            return false;
        }

        foreach ($section->teachers as $teacher) {
            if ($this->hasTeacherConflictInMemory($teacher, $timeslot)) {
                return false;
            }
            if (!$this->teacherCanAcceptTimeslot($teacher, $timeslot)) {
                return false;
            }
        }

        if (config('scheduling.enforce_student_conflicts', true)) {
            foreach ($this->sectionStudentIds[$section->id] ?? [] as $studentId) {
                if ($this->hasStudentConflictInMemory((int) $studentId, $timeslot)) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function isTimeslotAllowedForSection($section, $timeslot): bool
    {
        return StudentScheduleRules::timeslotAllowedForType(
            $this->getSectionStudentType($section),
            $timeslot
        );
    }

    protected function hasTeacherConflictInMemory($teacher, $timeslot): bool
    {
        foreach (array_keys($this->teacherTimeslotMap[$teacher->id] ?? []) as $existingTimeslotId) {
            $existing = $this->timeslots->firstWhere('id', (int) $existingTimeslotId);
            if (StudentScheduleRules::timeslotsOverlap($existing, $timeslot)) {
                return true;
            }
        }

        return false;
    }

    protected function hasStudentConflictInMemory(int $studentId, $timeslot): bool
    {
        foreach (array_keys($this->studentTimeslotMap[$studentId] ?? []) as $existingTimeslotId) {
            $existing = $this->timeslots->firstWhere('id', (int) $existingTimeslotId);
            if (StudentScheduleRules::timeslotsOverlap($existing, $timeslot)) {
                return true;
            }
        }

        return false;
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
        foreach ($this->sections as $section) {
            $course        = $section->courseOffering->course;
            $requiredSlots = $this->getRequiredWeeklyHours($course);

            while (!$this->sectionHasRequiredHours($section->id, $requiredSlots)) {
                if (!$this->placeOneSlotForSection($section, $course)) {
                    break;
                }
            }
        }

        return $this->currentSchedule;
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

            $currentHours = $this->getCurrentSectionScheduledHours($section->id);
            $result       = ! $this->sectionHasRequiredHours($section->id, $requiredSlots)
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
            $eligibleRooms = $this->getEligibleRoomsForSection($section, $course)
                ->filter(fn ($r) => $this->canUseRoomForSection($section, $r));

            if ($preferredRoomId) {
                $eligibleRooms = $eligibleRooms->where('id', (int) $preferredRoomId);
            }

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
                $slotHours = $this->getTimeslotTeachingHours($timeslot);
                $this->teacherTimeslotMap[$teacher->id][$timeslot->id] = true;
                $this->teacherHours[$teacher->id] = ($this->teacherHours[$teacher->id] ?? 0) + $slotHours;
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

        $cohort = $this->getSectionAcademicCohort($section);
        if ($cohort !== '') {
            $this->cohortRoomMap[$cohort] = $room->id;
            $this->roomCohortsMap[$room->id][$cohort] = true;
        }
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
                $slotHours = $this->getTimeslotTeachingHours($timeslot);
                unset($this->teacherTimeslotMap[$teacher->id][$timeslot->id]);
                $this->teacherHours[$teacher->id] = max(0, ($this->teacherHours[$teacher->id] ?? 0) - $slotHours);
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

            $cohort = $this->getSectionAcademicCohort($section);
            if ($cohort !== '' && !$this->cohortStillUsesRoom($cohort, $room->id)) {
                unset($this->roomCohortsMap[$room->id][$cohort]);
                if (empty($this->roomCohortsMap[$room->id])) {
                    unset($this->roomCohortsMap[$room->id]);
                }
                if (($this->cohortRoomMap[$cohort] ?? null) === $room->id) {
                    unset($this->cohortRoomMap[$cohort]);
                }
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

        $cohort = $this->getSectionAcademicCohort($section);

        $assignedRoomId = $this->sectionRoomMap[$section->id] ?? null;
        if ($assignedRoomId !== null && (int) $assignedRoomId !== (int) $room->id) {
            return false;
        }

        if ($cohort !== '') {
            $cohortRoom = $this->cohortRoomMap[$cohort] ?? null;
            if ($cohortRoom !== null && (int) $cohortRoom !== (int) $room->id) {
                return false;
            }
        }

        $roomCohorts = array_keys($this->roomCohortsMap[$room->id] ?? []);

        if ($cohort !== '' && in_array($cohort, $roomCohorts, true)) {
            return true;
        }

        $maxCohorts = (int) config('scheduling.room_max_sections', 2);
        if (count($roomCohorts) >= $maxCohorts) {
            return false;
        }

        if (empty($roomCohorts)) {
            return $this->projectedRoomCohortLoad($room->id, $cohort) <= (int) config('scheduling.room_combined_hours_limit', 38);
        }

        foreach ($roomCohorts as $existingCohort) {
            if ($cohort === '' || !$this->isAcademicCohortPairCompatible($cohort, $existingCohort)) {
                return false;
            }
        }

        return $this->projectedRoomCohortLoad($room->id, $cohort) <= (int) config('scheduling.room_combined_hours_limit', 38);
    }

    protected function projectedRoomCohortLoad(int $roomId, string $newCohort): int
    {
        $total = 0;
        foreach (array_keys($this->roomCohortsMap[$roomId] ?? []) as $cohort) {
            $total += $this->cohortWeeklyHours[$cohort] ?? $this->calculateCohortWeeklyHours($cohort);
        }

        if ($newCohort !== '' && !isset($this->roomCohortsMap[$roomId][$newCohort])) {
            $total += $this->cohortWeeklyHours[$newCohort] ?? $this->calculateCohortWeeklyHours($newCohort);
        }

        return $total;
    }

    protected function cohortStillUsesRoom(string $cohort, int $roomId): bool
    {
        foreach (array_keys($this->roomSectionsMap[$roomId] ?? []) as $sectionId) {
            $existing = $this->sections->firstWhere('id', $sectionId);
            if ($existing && $this->getSectionAcademicCohort($existing) === $cohort) {
                if ($this->getCurrentSectionSlotCount($sectionId) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function getRoomSelectionScore($section, $room): int
    {
        $cohort = $this->getSectionAcademicCohort($section);

        if ($cohort !== '' && ($this->cohortRoomMap[$cohort] ?? null) === $room->id) {
            return 1000;
        }

        $roomCohorts = array_keys($this->roomCohortsMap[$room->id] ?? []);

        if ($cohort !== '' && in_array($cohort, $roomCohorts, true)) {
            return 1000;
        }

        if (empty($roomCohorts)) {
            return 800;
        }

        $maxCohorts = (int) config('scheduling.room_max_sections', 2);
        if (count($roomCohorts) >= $maxCohorts) {
            return -1000;
        }

        $existingCohort = $roomCohorts[0];
        if ($cohort === '' || !$this->isAcademicCohortPairCompatible($cohort, $existingCohort)) {
            return -1000;
        }

        $parsedA = $this->parseCohortKey($cohort);
        $parsedB = $this->parseCohortKey($existingCohort);

        if ($parsedA && $parsedB && $parsedA['dept'] === $parsedB['dept'] && $cohort === $existingCohort) {
            return 900;
        }
        if ($parsedA && $parsedB && $parsedA['dept'] === $parsedB['dept'] && $this->isAdjacentBatch($cohort, $existingCohort)) {
            return 800;
        }
        if ($parsedA && $parsedB && $parsedA['dept'] !== $parsedB['dept'] && $parsedA['year'] === $parsedB['year']
            && $parsedA['suffix'] === $parsedB['suffix']
        ) {
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

    protected function getEligibleRoomsForSection($section, $course)
    {
        $cohort = $this->getSectionAcademicCohort($section);

        if ($cohort !== '' && isset($this->cohortRoomMap[$cohort])) {
            $homeRoom = $this->rooms->firstWhere('id', $this->cohortRoomMap[$cohort]);
            if ($homeRoom && $homeRoom->capacity >= $section->capacity) {
                return collect([$homeRoom]);
            }
        }

        return $this->getEligibleRooms($course);
    }

    protected function findAvailableRoom($section, $course, $timeslot)
    {
        return $this->getEligibleRoomsForSection($section, $course)
            ->filter(fn ($r) => $this->canUseRoomForSection($section, $r))
            ->sortByDesc(fn ($r) => $this->getRoomSelectionScore($section, $r))
            ->first(fn ($r) => !$this->hasRoomConflictInMemory($r, $timeslot));
    }

    protected function hasRoomConflictInMemory($room, $timeslot): bool
    {
        foreach (array_keys($this->roomTimeslotMap[$room->id] ?? []) as $existingTimeslotId) {
            $existing = $this->timeslots->firstWhere('id', (int) $existingTimeslotId);
            if (StudentScheduleRules::timeslotsOverlap($existing, $timeslot)) {
                return true;
            }
        }

        return false;
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

    protected function getSectionAcademicCohort($section): string
    {
        return trim((string) ($this->sectionCohortKeys[$section->id] ?? ''));
    }

    protected function getSectionStudentType($section): string
    {
        return $this->sectionStudentTypes[$section->id] ?? StudentScheduleRules::DEFAULT_TYPE;
    }

    protected function getSectionBatchKey($section): string
    {
        return $this->getSectionAcademicCohort($section);
    }

    protected function isAcademicCohortPairCompatible(string $cohortA, string $cohortB): bool
    {
        if ($cohortA === '' || $cohortB === '') {
            return false;
        }

        if ($cohortA === $cohortB) {
            return true;
        }

        $parsedA = $this->parseCohortKey($cohortA);
        $parsedB = $this->parseCohortKey($cohortB);

        if ($parsedA && $parsedB) {
            if ($parsedA['dept'] === $parsedB['dept'] && $this->isAdjacentBatch($cohortA, $cohortB)) {
                return true;
            }

            if ($parsedA['dept'] !== $parsedB['dept']
                && $parsedA['year'] === $parsedB['year']
                && $parsedA['suffix'] === $parsedB['suffix']
            ) {
                return true;
            }
        }

        return false;
    }

    protected function isSectionRoomPairCompatible($sectionA, $sectionB): bool
    {
        $cohortA = $this->getSectionAcademicCohort($sectionA);
        $cohortB = $this->getSectionAcademicCohort($sectionB);

        if ($cohortA !== '' && $cohortB !== '') {
            return $this->isAcademicCohortPairCompatible($cohortA, $cohortB);
        }

        $deptA  = $this->getSectionDepartmentId($sectionA);
        $deptB  = $this->getSectionDepartmentId($sectionB);
        $batchA = $this->getSectionBatchKey($sectionA);
        $batchB = $this->getSectionBatchKey($sectionB);

        if ($deptA === $deptB && $batchA === $batchB) {
            return true;
        }
        if ($deptA === $deptB && $this->isAdjacentBatch($batchA, $batchB)) {
            return true;
        }
        if ($deptA !== $deptB && $batchA === $batchB) {
            return true;
        }

        return false;
    }

    protected function isAdjacentBatch($batchA, $batchB): bool
    {
        if ($batchA === '' || $batchB === '' || $batchA === $batchB) {
            return false;
        }

        $parsedA = $this->parseCohortKey($batchA);
        $parsedB = $this->parseCohortKey($batchB);

        if ($parsedA && $parsedB && $parsedA['dept'] === $parsedB['dept']) {
            if ($parsedA['year'] === $parsedB['year']
                && $parsedA['suffix'] !== ''
                && $parsedB['suffix'] !== ''
                && abs(ord($parsedA['suffix']) - ord($parsedB['suffix'])) === 1
            ) {
                return true;
            }

            if ($parsedA['suffix'] === $parsedB['suffix']
                && abs($parsedA['year'] - $parsedB['year']) === 1
            ) {
                return true;
            }
        }

        if (is_numeric($batchA) && is_numeric($batchB)) {
            return abs((int) $batchA - (int) $batchB) === 1;
        }

        return false;
    }

    protected function parseCohortKey(string $cohort): ?array
    {
        if (preg_match('/^([A-Za-z]+)-(\d+)([A-Za-z]?)$/i', trim($cohort), $m)) {
            return [
                'dept'   => strtoupper($m[1]),
                'year'   => (int) $m[2],
                'suffix' => strtoupper($m[3]),
            ];
        }

        return null;
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
            'enrollments.student',
        ])
            ->whereHas('courseOffering', fn ($q) => $q->where('semester_id', $this->semesterId))
            ->get()
            ->filter(fn ($s) => $s->courseOffering && $s->courseOffering->course)
            ->values();

        $this->totalSectionCount = $this->sections->count();

        $this->teachers = Teacher::all();
        $this->rooms    = Room::all();

        $allowedDays = array_keys(config('scheduling.days', []));

        $this->timeslots = Timeslot::whereIn('day_of_week', $allowedDays)
            ->get()
            ->sortBy([
                fn ($a, $b) => $this->getDayIndex($a->day_of_week) <=> $this->getDayIndex($b->day_of_week),
                fn ($a, $b) => strcmp((string) $a->start_time, (string) $b->start_time),
            ])
            ->values();

        if ($this->timeslots->isEmpty()) {
            $this->timeslots = $this->createDefaultTimeslots();
        }

        $this->timeslotsByDay = $this->timeslots->groupBy('day_of_week');
        $this->resetSchedulingState();

        // Feasibility pre-check
        $totalRequired = $this->sections->sum(
            fn ($s) => $this->getRequiredWeeklyHours($s->courseOffering->course)
        );
        $weeklyTeachingHours = TimeslotDuration::totalWeeklyTeachingHours($this->timeslots);

        $weeklySlotCapacity = $weeklyTeachingHours * max(1, $this->rooms->count());

        if ($totalRequired > $weeklySlotCapacity + 0.001) {
            throw new \Exception(
                "Insufficient total capacity. Required: {$totalRequired} teaching hour(s), "
                ."available: {$weeklySlotCapacity} room-hour combination(s) across all timeslots."
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
        $allStudentIds = [];
        foreach ($this->sections as $section) {
            $ids = $section->enrollments->pluck('student_id')->toArray();
            $this->sectionStudentIds[$section->id] = $ids;
            $allStudentIds = array_merge($allStudentIds, $ids);
        }

        $studentsById = Student::whereIn('id', array_unique($allStudentIds))
            ->get(['id', 'first_name', 'last_name', 'academic_section', 'student_type'])
            ->keyBy('id');

        $this->studentTypeByStudent = $studentsById
            ->map(fn ($student) => StudentScheduleRules::normalizeStudentType($student->student_type))
            ->all();

        foreach ($this->sections as $section) {
            $counts = [];
            $studentTypes = [];
            foreach ($this->sectionStudentIds[$section->id] ?? [] as $studentId) {
                $student = $studentsById->get($studentId);
                $cohort = trim((string) ($student?->academic_section ?? ''));
                if ($cohort !== '') {
                    $counts[$cohort] = ($counts[$cohort] ?? 0) + 1;
                }

                $studentTypes[] = $this->studentTypeByStudent[$studentId] ?? StudentScheduleRules::DEFAULT_TYPE;
            }

            if ($counts !== []) {
                arsort($counts);
                $this->sectionCohortKeys[$section->id] = (string) array_key_first($counts);
            } else {
                $this->sectionCohortKeys[$section->id] = '';
            }

            $studentTypes = array_values(array_unique($studentTypes));
            $this->sectionStudentTypes[$section->id] = count($studentTypes) <= 1
                ? ($studentTypes[0] ?? StudentScheduleRules::DEFAULT_TYPE)
                : StudentScheduleRules::MIXED_TYPE;
        }

        $this->cohortWeeklyHours = [];
        foreach ($this->sections as $section) {
            $cohort = $this->getSectionAcademicCohort($section);
            if ($cohort === '') {
                continue;
            }
            $this->cohortWeeklyHours[$cohort] = ($this->cohortWeeklyHours[$cohort] ?? 0)
                + $this->getRequiredWeeklyHours($section->courseOffering->course);
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

        $this->assertSchedulingFeasibility();
    }

    /**
     * Fail early when hard constraints make a full timetable impossible.
     */
    protected function assertSchedulingFeasibility(): void
    {
        $timeslotCount      = $this->timeslots->count();
        $roomCount          = $this->rooms->count();
        $maxCohortsPerRoom  = (int) config('scheduling.room_max_sections', 2);
        $roomHoursLimit     = (int) config('scheduling.room_combined_hours_limit', 38);

        $academicCohorts = array_keys(array_filter($this->cohortWeeklyHours));
        $cohortCount     = count($academicCohorts);
        $requiredHoursByStudentType = [];

        foreach ($this->sections as $section) {
            $studentType = $this->getSectionStudentType($section);
            if ($studentType === StudentScheduleRules::MIXED_TYPE) {
                throw new \Exception(
                    "Section {$section->section_name} contains both regular and weekend students. "
                    .'Split the enrollment into separate course sections before generating schedules.'
                );
            }

            $requiredHours = $this->getRequiredWeeklyHours($section->courseOffering->course);
            $allowedHours = StudentScheduleRules::allowedTeachingHoursForType($studentType, $this->timeslots);

            if ($allowedHours + 0.001 < $requiredHours) {
                throw new \Exception(
                    "Section {$section->section_name} needs {$requiredHours} weekly teaching hour(s), "
                    ."but {$studentType} students only have {$allowedHours} allowed weekly timeslot hour(s). "
                    .'Import or create more allowed timeslots.'
                );
            }

            $requiredHoursByStudentType[$studentType] = ($requiredHoursByStudentType[$studentType] ?? 0) + $requiredHours;
        }

        if ($cohortCount > 0) {
            $minRoomsRequired = (int) ceil($cohortCount / max(1, $maxCohortsPerRoom));

            if ($roomCount < $minRoomsRequired) {
                throw new \Exception(
                    "Not enough classrooms: {$cohortCount} student academic section(s) require at least {$minRoomsRequired} "
                    ."room(s) when at most {$maxCohortsPerRoom} academic sections may share one room, but only {$roomCount} "
                    .'room(s) are available. Import or add more rooms.'
                );
            }

            foreach ($this->cohortWeeklyHours as $cohort => $hours) {
                if ($hours > $roomHoursLimit) {
                    throw new \Exception(
                        "Academic section {$cohort} requires {$hours} weekly teaching hour(s), "
                        ."which exceeds the {$roomHoursLimit}-hour limit for a single classroom. "
                        .'Reduce course credits or split the academic section.'
                    );
                }
            }
        } else {
            $sectionCount     = $this->sections->count();
            $minRoomsRequired = (int) ceil($sectionCount / max(1, $maxCohortsPerRoom));

            if ($roomCount < $minRoomsRequired) {
                throw new \Exception(
                    "Not enough classrooms: {$sectionCount} course section(s) require at least {$minRoomsRequired} "
                    ."room(s), but only {$roomCount} room(s) are available. Import enrollments (to link students to "
                    .'academic sections) or add more rooms.'
                );
            }
        }

        $totalRequiredSlots = (int) $this->sections->sum(
            fn ($s) => $this->getRequiredWeeklyHours($s->courseOffering->course)
        );
        $weeklyTeachingHours = TimeslotDuration::totalWeeklyTeachingHours($this->timeslots);
        $weeklySlotCapacity    = $weeklyTeachingHours * $roomCount;
        if ($totalRequiredSlots > $weeklySlotCapacity + 0.001) {
            throw new \Exception(
                "Insufficient weekly capacity: {$totalRequiredSlots} teaching hour(s) are required, "
                ."but only {$weeklySlotCapacity} room-hour combinations exist "
                ."({$weeklyTeachingHours} timeslot hours × {$roomCount} rooms)."
            );
        }

        if (config('scheduling.enforce_student_conflicts', true)) {
            $hoursByStudent = [];
            foreach ($this->sections as $section) {
                $hours = $this->getRequiredWeeklyHours($section->courseOffering->course);
                foreach ($this->sectionStudentIds[$section->id] ?? [] as $studentId) {
                    $hoursByStudent[$studentId] = ($hoursByStudent[$studentId] ?? 0) + $hours;
                }
            }

            foreach ($hoursByStudent as $studentId => $hours) {
                if ($hours > $weeklyTeachingHours + 0.001) {
                    $student = Student::find($studentId);
                    $label   = $student
                        ? "{$student->first_name} {$student->last_name} ({$student->academic_section})"
                        : "ID {$studentId}";

                    throw new \Exception(
                        "Student {$label} is enrolled in courses totaling {$hours} weekly hour(s), "
                        ."but only {$weeklyTeachingHours} teaching hour(s) exist in the weekly timeslots. "
                        .'Reduce enrollments or add more timeslots.'
                    );
                }
            }
        }

        $hoursByTeacher = [];
        foreach ($this->sections as $section) {
            $hours = $this->getRequiredWeeklyHours($section->courseOffering->course);
            foreach ($section->teachers as $teacher) {
                $hoursByTeacher[$teacher->id] = ($hoursByTeacher[$teacher->id] ?? 0) + $hours;
            }
        }

        foreach ($hoursByTeacher as $teacherId => $hours) {
            $teacher = $this->teachers->firstWhere('id', $teacherId);
            if (!$teacher) {
                continue;
            }
            $cap = $this->getTeacherMaxWeeklyHours($teacher);
            if ($hours > $cap) {
                throw new \Exception(
                    "Teacher {$teacher->full_name} is assigned {$hours} weekly teaching hour(s) from course credits, "
                    ."above the {$cap}-hour limit. Adjust teacher load or section assignments."
                );
            }
        }
    }

    protected function sortSectionsByDifficulty()
    {
        return $this->sections->sortBy(function ($section) {
            return $this->getSectionAcademicCohort($section);
        })->sortByDesc(function ($section) {
            $difficulty  = count($this->sectionStudentIds[$section->id] ?? []) * 10;
            $difficulty += (10 - min(10, $section->teachers->count())) * 5;
            $difficulty += $this->getRequiredWeeklyHours($section->courseOffering->course) * 2;

            $eligibleRooms = $this->getEligibleRoomsForSection($section, $section->courseOffering->course)->count();
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

            while (!$this->sectionHasRequiredHours($sectionId, $required)) {
                $placed = $this->placeOneSlotForSection($section, $course);

                if (!$placed) {
                    Log::warning('Completion pass could not place slot', [
                        'section_id'   => $sectionId,
                        'section_name' => $section->section_name,
                        'scheduled'    => $this->getCurrentSectionScheduledHours($sectionId),
                        'required'     => $required,
                    ]);
                    break;
                }
            }
        }

        return $this->filterSemesterSchedule($this->currentSchedule);
    }

    protected function placeOneSlotForSection($section, $course): bool
    {
        $eligibleRooms = $this->getEligibleRoomsForSection($section, $course)
            ->filter(fn ($r) => $this->canUseRoomForSection($section, $r));

        $cohortRoomId = $this->getSectionAcademicCohort($section) !== ''
            ? ($this->cohortRoomMap[$this->getSectionAcademicCohort($section)] ?? null)
            : null;
        $preferredRoomId = $this->sectionRoomMap[$section->id] ?? $cohortRoomId;

        if ($preferredRoomId) {
            $eligibleRooms = $eligibleRooms->where('id', (int) $preferredRoomId);
        }

        $candidates = collect();
        foreach ($this->timeslots as $timeslot) {
            if (!$this->isTimeslotValid($section, $course, $timeslot)) {
                continue;
            }

            foreach ($eligibleRooms as $room) {
                if ($this->hasRoomConflictInMemory($room, $timeslot)) {
                    continue;
                }

                $candidates->push([
                    'timeslots' => collect([$timeslot]),
                    'room'      => $room,
                    'day'       => $timeslot->day_of_week,
                    'score'     => $this->calculateAssignmentScore($section, $course, [$timeslot], $room),
                ]);
            }
        }

        $best = $candidates->sortByDesc('score')->first();
        if (!$best) {
            return false;
        }

        $this->applyAssignment($section, $best);

        return true;
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
        $this->cohortRoomMap               = [];
        $this->roomCohortsMap              = [];
        $this->teacherScheduledDays        = [];
        $this->warnedRoomFallbackCourses   = [];
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
            'attempts'          => (int) config('scheduling.max_generation_attempts', 5),
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
            ['start' => '08:00', 'end' => '09:00'],
            ['start' => '09:00', 'end' => '10:00'],
            ['start' => '10:00', 'end' => '11:00'],
            ['start' => '11:00', 'end' => '12:00'],
            ['start' => '13:00', 'end' => '14:00'],
            ['start' => '14:00', 'end' => '15:00'],
            ['start' => '15:00', 'end' => '16:00'],
            ['start' => '16:00', 'end' => '17:00'],
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

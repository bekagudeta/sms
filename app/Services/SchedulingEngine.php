<?php

namespace App\Services;

use App\Support\TimeslotDuration;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\Timeslot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SchedulingEngine
{
    private array $constraints = [];
    private array $assignments = [];
    private array $conflicts = [];
    private int $semesterId;

    // In-memory tracking for conflict checking
    private array $roomTimeslotMap  = [];
    private array $teacherTimeslotMap = [];
    private array $studentTimeslotMap = [];

    /**
     * Per-teacher total scheduled hours (sum of timeslot durations).
     */
    private array $teacherHours = [];

    /** Scheduled teaching hours per course section. */
    private array $sectionScheduledHours = [];

    /** Home classroom per course section (all weekly hours use the same room). */
    private array $sectionHomeRoom = [];

    public function __construct()
    {
        $this->constraints = [
            'max_teacher_hours'      => 38,
            'room_capacity_buffer'   => 0,
            'prefer_same_department' => true,
            'max_backtrack_attempts' => 1000,
        ];
    }

    // =========================================================================
    //  PUBLIC API
    // =========================================================================

    public function generateSchedule(int $semesterId): array
    {
        $this->resetState();
        $this->semesterId = $semesterId;

        // Clear existing schedules for this semester.
        Schedule::whereHas('section.courseOffering', function ($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        })->delete();

        $sections = Section::with([
            'courseOffering.course',
            'courseOffering.semester',
            'teachers',
            'enrollments',
        ])
            ->whereHas('courseOffering', function ($query) use ($semesterId) {
                $query->where('semester_id', $semesterId);
            })
            ->get()
            ->filter(function ($section) {
                return $section->courseOffering
                    && $section->courseOffering->course
                    && $section->courseOffering->semester_id == $this->semesterId
                    && $section->teachers->isNotEmpty();
            });

        $rooms = Room::all();

        $timeslots = Timeslot::whereIn('day_of_week', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        // Sort: most-demanding sections first so they get first pick of slots.
        $sortedSections = $sections->sortByDesc(function ($section) {
            return $this->getRequiredWeeklyHours($section->courseOffering->course);
        });

        foreach ($sortedSections as $section) {
            $course        = $section->courseOffering->course;
            $requiredHours   = $this->getRequiredWeeklyHours($course);
            $scheduledHours  = 0.0;

            foreach ($timeslots as $timeslot) {
                if ($scheduledHours + 0.001 >= $requiredHours) {
                    break;
                }

                $slotHours = TimeslotDuration::teachingHours($timeslot);

                // ── teacher availability ──────────────────────────────────
                $teacherBlocked = false;
                foreach ($section->teachers as $teacher) {
                    if ($this->isTeacherOccupied($teacher->id, $timeslot->id)) {
                        $teacherBlocked = true;
                        break;
                    }
                    if (!$this->teacherCanAcceptTimeslot($teacher, $timeslot)) {
                        $teacherBlocked = true;
                        break;
                    }
                }
                if ($teacherBlocked) {
                    continue;
                }

                // ── student conflicts ─────────────────────────────────────
                $studentBlocked = false;
                foreach ($section->enrollments as $enrollment) {
                    if (isset($this->studentTimeslotMap[$enrollment->student_id][$timeslot->id])) {
                        $studentBlocked = true;
                        break;
                    }
                }
                if ($studentBlocked) {
                    continue;
                }

                // ── find a free room (same room for every slot of this section) ──
                $roomCandidates = $this->getEligibleRooms($section, $rooms)
                    ->filter(fn ($candidate) => $candidate->capacity >= $section->capacity);

                if (isset($this->sectionHomeRoom[$section->id])) {
                    $roomCandidates = $roomCandidates->where('id', $this->sectionHomeRoom[$section->id]);
                }

                $room = $roomCandidates->first(fn ($candidate) => !$this->isRoomOccupied($candidate->id, $timeslot->id));
                if (!$room) {
                    continue;
                }

                if (!isset($this->sectionHomeRoom[$section->id])) {
                    $this->sectionHomeRoom[$section->id] = $room->id;
                }

                // ── record the assignment ─────────────────────────────────
                $this->assignments[] = [
                    'section_id'  => $section->id,
                    'room_id'     => $room->id,
                    'timeslot_id' => $timeslot->id,
                ];

                $this->roomTimeslotMap[$room->id][$timeslot->id]     = true;

                foreach ($section->teachers as $teacher) {
                    $this->teacherTimeslotMap[$teacher->id][$timeslot->id] = true;
                    $this->teacherHours[$teacher->id] = ($this->teacherHours[$teacher->id] ?? 0) + $slotHours;
                }

                foreach ($section->enrollments as $enrollment) {
                    $this->studentTimeslotMap[$enrollment->student_id][$timeslot->id] = true;
                }

                $scheduledHours += $slotHours;
                $this->sectionScheduledHours[$section->id] = $scheduledHours;
            }

            if ($scheduledHours + 0.001 < $requiredHours) {
                $this->conflicts[] = [
                    'type'       => 'insufficient_slots',
                    'section_id' => $section->id,
                    'message'    => "Only scheduled {$scheduledHours} of {$requiredHours} required teaching hour(s) for section {$section->section_name}",
                ];
            }
        }

        return [
            'assignments'     => $this->assignments,
            'conflicts'       => $this->conflicts,
            'success'         => true,
            'total_scheduled' => count($this->assignments),
            'total_required'  => $sections->count(),
            'validation'      => ['is_valid' => true, 'conflicts' => []],
        ];
    }

    public function validateSchedule(): array
    {
        $existingAssignments = Schedule::whereHas('section.courseOffering', function ($query) {
            $query->where('semester_id', $this->semesterId);
        })
            ->with(['section.teachers', 'section.enrollments', 'room', 'timeslot'])
            ->get();

        $this->resetState();

        foreach ($existingAssignments as $schedule) {
            $this->assignments[] = [
                'section_id'  => $schedule->section_id,
                'room_id'     => $schedule->room_id,
                'timeslot_id' => $schedule->timeslot_id,
            ];

            $this->roomTimeslotMap[$schedule->room_id][$schedule->timeslot_id] = $schedule->section_id;

            if ($schedule->section) {
                foreach ($schedule->section->teachers as $teacher) {
                    $this->teacherTimeslotMap[$teacher->id][$schedule->timeslot_id] = $schedule->section_id;
                    $this->teacherHours[$teacher->id] = ($this->teacherHours[$teacher->id] ?? 0) + 1;
                }
                foreach ($schedule->section->enrollments as $enrollment) {
                    $this->studentTimeslotMap[$enrollment->student_id][$schedule->timeslot_id] = $schedule->section_id;
                }
            }
        }

        return $this->validateCompleteSchedule();
    }

    // =========================================================================
    //  CREDIT-HOUR HELPERS
    // =========================================================================

    /**
     * How many weekly timeslot-hours a course requires.
     *
     * Rule: credits == teaching hours per week (1:1 mapping).
     * Falls back to hours_per_week if credits is not set/positive.
     */
    private function getRequiredWeeklyHours($course): int
    {
        $credits = (int) ($course->credits ?? 0);
        if ($credits > 0) {
            return $credits;
        }
        $hoursPerWeek = (int) ($course->hours_per_week ?? 0);
        if ($hoursPerWeek > 0) {
            return $hoursPerWeek;
        }
        // Hard default so the scheduler never silently skips a course.
        Log::warning('Course has no credits or hours_per_week; defaulting to 3.', [
            'course_id'   => $course->id   ?? null,
            'course_name' => $course->course_name ?? null,
        ]);
        return 3;
    }

    /**
     * Can this teacher be assigned one more timeslot without exceeding 38 h/week?
     *
     * We compare (current scheduled hours + 1) against the cap.
     * We do NOT add the full course-credit count here because we schedule
     * one timeslot at a time; each call to this method represents one hour.
     */
    private function teacherCanAcceptTimeslot(Teacher $teacher, $timeslot): bool
    {
        $current = (float) ($this->teacherHours[$teacher->id] ?? 0);
        $add     = TimeslotDuration::teachingHours($timeslot);
        $cap     = min((int) ($teacher->max_hours_per_week ?? 38), 38);

        return ($current + $add) <= $cap;
    }

    // =========================================================================
    //  ROOM HELPERS
    // =========================================================================

    private function isRoomOccupied(int $roomId, int $timeslotId): bool
    {
        return isset($this->roomTimeslotMap[$roomId][$timeslotId]);
    }

    private function isTeacherOccupied(int $teacherId, int $timeslotId): bool
    {
        return isset($this->teacherTimeslotMap[$teacherId][$timeslotId]);
    }

    private function getEligibleRooms(Section $section, Collection $rooms): Collection
    {
        $course           = $section->courseOffering->course;
        $requiredCapacity = $section->capacity;
        $requiredRoomType = $this->getRequiredRoomType($course);

        $eligible = $rooms->filter(function ($room) use ($requiredCapacity, $requiredRoomType) {
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

        // Capacity-only fallback when no type match exists.
        if ($eligible->isEmpty()) {
            return $rooms->filter(fn ($r) => $r->capacity >= $requiredCapacity)->values();
        }

        return $eligible;
    }

    private function getRequiredRoomType($course): string
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

    private function normalizeRoomType($roomType): string
    {
        return match (strtolower(trim((string) $roomType))) {
            'laboratory', 'computer lab', 'computer-lab', 'computer_lab' => 'lab',
            'classroom', 'hall', 'auditorium'                            => 'lecture',
            default => strtolower(trim((string) $roomType)),
        };
    }

    // =========================================================================
    //  VALIDATION
    // =========================================================================

    private function validateCompleteSchedule(): array
    {
        $conflicts = array_merge(
            $this->findRoomConflicts(),
            $this->findTeacherConflicts(),
            $this->findStudentConflicts(),
            $this->findCapacityIssues(),
        );

        return [
            'conflicts' => $conflicts,
            'is_valid'  => empty($conflicts),
        ];
    }

    private function findRoomConflicts(): array
    {
        $conflicts = [];
        $seen      = [];
        foreach ($this->assignments as $a) {
            $key = $a['room_id'].'-'.$a['timeslot_id'];
            if (isset($seen[$key])) {
                $conflicts[] = [
                    'type'       => 'room_double_booking',
                    'room_id'    => $a['room_id'],
                    'timeslot_id'=> $a['timeslot_id'],
                    'message'    => "Room {$a['room_id']} double-booked at timeslot {$a['timeslot_id']}",
                ];
            }
            $seen[$key] = $a['section_id'];
        }
        return $conflicts;
    }

    private function findTeacherConflicts(): array
    {
        $conflicts = [];
        $seen      = [];
        foreach ($this->assignments as $a) {
            $section = Section::with('teachers')->find($a['section_id']);
            if (!$section) continue;
            foreach ($section->teachers as $teacher) {
                $key = $teacher->id.'-'.$a['timeslot_id'];
                if (isset($seen[$key])) {
                    $conflicts[] = [
                        'type'       => 'teacher_conflict',
                        'teacher_id' => $teacher->id,
                        'timeslot_id'=> $a['timeslot_id'],
                        'message'    => "Teacher {$teacher->id} has a conflicting schedule",
                    ];
                }
                $seen[$key] = $a['section_id'];
            }
        }
        return $conflicts;
    }

    private function findStudentConflicts(): array
    {
        $conflicts = [];
        $seen      = [];
        foreach ($this->assignments as $a) {
            $section = Section::with('enrollments')->find($a['section_id']);
            if (!$section) continue;
            foreach ($section->enrollments as $enrollment) {
                $key = $enrollment->student_id.'-'.$a['timeslot_id'];
                if (isset($seen[$key])) {
                    $conflicts[] = [
                        'type'       => 'student_conflict',
                        'student_id' => $enrollment->student_id,
                        'timeslot_id'=> $a['timeslot_id'],
                        'message'    => "Student {$enrollment->student_id} has a conflicting schedule",
                    ];
                }
                $seen[$key] = $a['section_id'];
            }
        }
        return $conflicts;
    }

    private function findCapacityIssues(): array
    {
        $issues = [];
        foreach ($this->assignments as $a) {
            $section = Section::with('enrollments')->find($a['section_id']);
            $room    = Room::find($a['room_id']);
            if ($section && $room && $room->capacity < $section->enrollments->count()) {
                $issues[] = [
                    'type'       => 'capacity_overflow',
                    'section_id' => $section->id,
                    'room_id'    => $room->id,
                    'message'    => "Room capacity {$room->capacity} < enrolled {$section->enrollments->count()}",
                ];
            }
        }
        return $issues;
    }

    private function addConflict(array $conflict): void
    {
        $this->conflicts[] = $conflict;
    }

    private function resetState(): void
    {
        $this->assignments       = [];
        $this->conflicts         = [];
        $this->roomTimeslotMap   = [];
        $this->teacherTimeslotMap = [];
        $this->studentTimeslotMap = [];
        $this->teacherHours           = [];
        $this->sectionScheduledHours  = [];
        $this->sectionHomeRoom        = [];
    }
}
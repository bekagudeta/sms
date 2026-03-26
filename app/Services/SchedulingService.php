<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Section;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\Room;
use App\Models\Timeslot;
use Illuminate\Support\Facades\DB;

class SchedulingService
{

    public function generateSchedule($semesterId)
    {
        DB::beginTransaction();

        try {

            // Remove previous schedules for this semester
            Schedule::whereHas('section.courseOffering', function($q) use ($semesterId) {
                $q->where('semester_id', $semesterId);
            })->delete();

            $courseOfferings = CourseOffering::where('semester_id', $semesterId)
                ->with(['course', 'sections.teachers'])
                ->get();

            $teachers = Teacher::all();
            $rooms = Room::all();
            $timeslots = Timeslot::orderBy('day_of_week')->orderBy('start_time')->get();

            $scheduled = 0;

            foreach ($courseOfferings as $courseOffering) {
                foreach ($courseOffering->sections as $section) {
                    $result = $this->scheduleSingleSection(
                        $section,
                        $teachers,
                        $rooms,
                        $timeslots
                    );

                    if ($result) {
                        $scheduled++;
                    }
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => "Scheduled {$scheduled} sections successfully",
                'scheduled_count' => $scheduled
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Scheduling failed: ' . $e->getMessage()
            ];
        }
    }

    protected function scheduleSingleSection($section, $teachers, $rooms, $timeslots)
    {
        // If section already has a schedule, skip
        if ($section->schedule) {
            return true;
        }

        foreach ($rooms as $room) {
            if (!$this->isRoomEligible($room, $section)) {
                continue;
            }

            foreach ($timeslots as $timeslot) {
                if ($this->hasRoomConflict($room, $timeslot)) {
                    continue;
                }

                if ($this->hasTeacherConflict($section, $timeslot)) {
                    continue;
                }

                if ($this->hasStudentConflict($section, $timeslot)) {
                    continue;
                }

                return Schedule::create([
                    'section_id' => $section->id,
                    'room_id' => $room->id,
                    'timeslot_id' => $timeslot->id,
                ]);
            }
        }

        return null;
    }

    protected function isTeacherEligible($teacher, $course)
    {
        return $teacher->department_id == $course->department_id;
    }

    protected function isRoomEligible($room, $section)
    {
        return $room->capacity >= $section->capacity;
    }

    protected function hasTeacherConflict($section, $timeslot)
    {
        foreach ($section->teachers as $teacher) {
            $conflict = Schedule::whereHas('section.teachers', function($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            })
            ->where('timeslot_id', $timeslot->id)
            ->exists();

            if ($conflict) {
                return true;
            }
        }
        return false;
    }

    protected function hasRoomConflict($room, $timeslot)
    {
        return Schedule::where('room_id', $room->id)
            ->where('timeslot_id', $timeslot->id)
            ->exists();
    }

    protected function hasStudentConflict($section, $timeslot)
    {
        // Check if any student in this section has another class at the same time
        return Schedule::where('timeslot_id', $timeslot->id)
            ->whereHas('section.enrollments', function($q) use ($section) {
                $q->whereIn('student_id', $section->enrollments->pluck('student_id'));
            })
            ->exists();
    }

    protected function checkTeacherWeeklyLoad($teacher, $semesterId)
    {
        // Count schedules where teacher is assigned through section_teachers relationship
        $hours = Schedule::whereHas('section.teachers', function($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            })
            ->whereHas('section.courseOffering', function($q) use ($semesterId) {
                $q->where('semester_id', $semesterId);
            })
            ->count();

        return $hours < $teacher->max_hours_per_week;
    }

    protected function checkTeacherDailyLoad($teacher, $timeslot, $semesterId)
    {
        // Count schedules where teacher is assigned through section_teachers on the same day
        $hours = Schedule::whereHas('section.teachers', function($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            })
            ->whereHas('section.courseOffering', function($q) use ($semesterId) {
                $q->where('semester_id', $semesterId);
            })
            ->whereHas('timeslot', function ($q) use ($timeslot) {
                $q->where('day_of_week', $timeslot->day_of_week ?? $timeslot->day);
            })
            ->count();

        return $hours < ($teacher->max_hours_per_day ?? 6);
    }

    public function assignTeacher($scheduleId, $teacherId)
    {
        $schedule = Schedule::findOrFail($scheduleId);
        $teacher = Teacher::findOrFail($teacherId);

        // Check if teacher is already assigned to this section
        if ($schedule->section->teachers->contains($teacher)) {
            return [
                'success' => false,
                'message' => 'Teacher already assigned to this section'
            ];
        }

        // Check for time conflict
        $conflict = Schedule::whereHas('section.teachers', function($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })
        ->where('timeslot_id', $schedule->timeslot_id)
        ->exists();

        if ($conflict) {
            return [
                'success' => false,
                'message' => 'Teacher time conflict'
            ];
        }

        $schedule->section->teachers()->attach($teacherId);

        return [
            'success' => true,
            'message' => 'Teacher assigned'
        ];
    }

    public function assignRoom($scheduleId, $roomId)
    {
        $schedule = Schedule::findOrFail($scheduleId);
        $room = Room::findOrFail($roomId);

        if (!$this->isRoomEligible($room, $schedule->section)) {
            return [
                'success' => false,
                'message' => 'Room not suitable'
            ];
        }

        if ($this->hasRoomConflict($room, $schedule->timeslot)) {
            return [
                'success' => false,
                'message' => 'Room conflict'
            ];
        }

        $schedule->room_id = $roomId;
        $schedule->save();

        return [
            'success' => true,
            'message' => 'Room assigned'
        ];
    }

    public function assignTimeslot($scheduleId, $timeslotId)
    {

        $schedule = Schedule::findOrFail($scheduleId);
        $timeslot = Timeslot::findOrFail($timeslotId);

        // Check teacher conflicts through section_teachers relationship
        foreach ($schedule->section->teachers as $teacher) {
            $conflict = Schedule::whereHas('section.teachers', function($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            })
            ->where('timeslot_id', $timeslot->id)
            ->where('id', '!=', $schedule->id)
            ->exists();

            if ($conflict) {
                return [
                    'success' => false,
                    'message' => 'Teacher time conflict'
                ];
            }
        }

        if ($this->hasRoomConflict($schedule->room, $timeslot)) {

            return [
                'success' => false,
                'message' => 'Room conflict'
            ];
        }

        // Check student conflicts
        if ($this->hasStudentConflict($schedule->section, $timeslot)) {

            return [
                'success' => false,
                'message' => 'Student conflict'
            ];
        }

        $schedule->timeslot_id = $timeslotId;
        $schedule->save();

        return [
            'success' => true,
            'message' => 'Timeslot assigned'
        ];
    }
}
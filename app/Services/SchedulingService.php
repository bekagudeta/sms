<?php

namespace App\Services;

use App\Models\Course;
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

            // Remove previous schedules
            Schedule::where('semester_id', $semesterId)->delete();

            $courses = Course::where('semester_id', $semesterId)
                ->orderByDesc('student_count') // harder courses first
                ->get();

            $teachers = Teacher::all();
            $rooms = Room::all();
            $timeslots = Timeslot::orderBy('day')->orderBy('start_time')->get();

            $scheduled = 0;

            foreach ($courses as $course) {

                $blocks = $course->hours_per_week;

                while ($blocks > 0) {

                    $result = $this->scheduleSingleBlock(
                        $course,
                        $teachers,
                        $rooms,
                        $timeslots,
                        $semesterId
                    );

                    if (!$result) {
                        break;
                    }

                    $scheduled++;
                    $blocks--;
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Schedule generated successfully',
                'scheduled_blocks' => $scheduled
            ];

        } catch (\Exception $e) {

            DB::rollBack();

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    protected function scheduleSingleBlock($course, $teachers, $rooms, $timeslots, $semesterId)
    {

        foreach ($teachers as $teacher) {

            if (!$this->isTeacherEligible($teacher, $course)) {
                continue;
            }

            if (!$this->checkTeacherWeeklyLoad($teacher, $semesterId)) {
                continue;
            }

            foreach ($rooms as $room) {

                if (!$this->isRoomEligible($room, $course)) {
                    continue;
                }

                foreach ($timeslots as $timeslot) {

                    if ($this->hasTeacherConflict($teacher, $timeslot, $semesterId)) {
                        continue;
                    }

                    if ($this->hasRoomConflict($room, $timeslot, $semesterId)) {
                        continue;
                    }

                    if ($this->hasStudentConflict($course, $timeslot, $semesterId)) {
                        continue;
                    }

                    if (!$this->checkTeacherDailyLoad($teacher, $timeslot, $semesterId)) {
                        continue;
                    }

                    return Schedule::create([
                        'course_id' => $course->id,
                        'teacher_id' => $teacher->id,
                        'room_id' => $room->id,
                        'timeslot_id' => $timeslot->id,
                        'semester_id' => $semesterId,
                        'section' => $course->section ?? 'A',
                        'max_students' => $room->capacity,
                        'status' => 'scheduled'
                    ]);
                }
            }
        }

        return null;
    }

    protected function isTeacherEligible($teacher, $course)
    {
        return $teacher->department_id == $course->department_id;
    }

    protected function isRoomEligible($room, $course)
    {

        if ($room->capacity < $course->student_count) {
            return false;
        }

        if ($course->room_type && $room->type != $course->room_type) {
            return false;
        }

        return true;
    }

    protected function hasTeacherConflict($teacher, $timeslot, $semesterId)
    {

        return Schedule::where('teacher_id', $teacher->id)
            ->where('timeslot_id', $timeslot->id)
            ->where('semester_id', $semesterId)
            ->exists();
    }

    protected function hasRoomConflict($room, $timeslot, $semesterId)
    {

        return Schedule::where('room_id', $room->id)
            ->where('timeslot_id', $timeslot->id)
            ->where('semester_id', $semesterId)
            ->exists();
    }

    protected function hasStudentConflict($course, $timeslot, $semesterId)
    {

        return Schedule::where('timeslot_id', $timeslot->id)
            ->where('semester_id', $semesterId)
            ->whereHas('course', function ($query) use ($course) {

                $query->where('department_id', $course->department_id)
                    ->where('level', $course->level)
                    ->where('section', $course->section);

            })
            ->exists();
    }

    protected function checkTeacherWeeklyLoad($teacher, $semesterId)
    {

        $hours = Schedule::where('teacher_id', $teacher->id)
            ->where('semester_id', $semesterId)
            ->count();

        return $hours < $teacher->max_hours_per_week;
    }

    protected function checkTeacherDailyLoad($teacher, $timeslot, $semesterId)
    {

        $hours = Schedule::where('teacher_id', $teacher->id)
            ->where('semester_id', $semesterId)
            ->whereHas('timeslot', function ($q) use ($timeslot) {
                $q->where('day', $timeslot->day);
            })
            ->count();

        return $hours < $teacher->max_hours_per_day;
    }

    public function assignTeacher($scheduleId, $teacherId)
    {

        $schedule = Schedule::findOrFail($scheduleId);
        $teacher = Teacher::findOrFail($teacherId);

        if ($this->hasTeacherConflict($teacher, $schedule->timeslot, $schedule->semester_id)) {

            return [
                'success' => false,
                'message' => 'Teacher conflict'
            ];
        }

        $schedule->teacher_id = $teacherId;
        $schedule->save();

        return [
            'success' => true,
            'message' => 'Teacher assigned'
        ];
    }

    public function assignRoom($scheduleId, $roomId)
    {

        $schedule = Schedule::findOrFail($scheduleId);
        $room = Room::findOrFail($roomId);
        $course = $schedule->course;

        if (!$this->isRoomEligible($room, $course)) {

            return [
                'success' => false,
                'message' => 'Room not suitable'
            ];
        }

        if ($this->hasRoomConflict($room, $schedule->timeslot, $schedule->semester_id)) {

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

        if ($this->hasTeacherConflict($schedule->teacher, $timeslot, $schedule->semester_id)) {

            return [
                'success' => false,
                'message' => 'Teacher conflict'
            ];
        }

        if ($this->hasRoomConflict($schedule->room, $timeslot, $schedule->semester_id)) {

            return [
                'success' => false,
                'message' => 'Room conflict'
            ];
        }

        if ($this->hasStudentConflict($schedule->course, $timeslot, $schedule->semester_id)) {

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
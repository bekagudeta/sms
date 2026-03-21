<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Schedule;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\Room;
use App\Models\Timeslot;
use App\Models\Semester;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $semesters = Semester::all();
        $rooms = Room::all();
        $timeslots = Timeslot::all();

        foreach (Course::all() as $course) {
            // Pick a random teacher from same department
            $teacher = Teacher::where('department_id', $course->department_id)->inRandomOrder()->first();

            foreach ($semesters as $semester) {
                // Find a timeslot and room that are free for this teacher and semester
                foreach ($timeslots as $slot) {
                    $availableRoom = $rooms->first(function($room) use ($slot, $semester) {
                        return !Schedule::where('room_id', $room->id)
                                        ->where('timeslot_id', $slot->id)
                                        ->where('semester_id', $semester->id)
                                        ->exists();
                    });

                    if ($availableRoom) {
                        // Check teacher conflict
                        $teacherFree = !Schedule::where('teacher_id', $teacher->id)
                                                ->where('timeslot_id', $slot->id)
                                                ->where('semester_id', $semester->id)
                                                ->exists();

                        if ($teacherFree) {
                            Schedule::create([
                                'course_id' => $course->id,
                                'teacher_id' => $teacher->id,
                                'room_id' => $availableRoom->id,
                                'timeslot_id' => $slot->id,
                                'semester_id' => $semester->id,
                                'day' => $slot->day_of_week,
                                'start_time' => $slot->start_time,
                                'end_time' => $slot->end_time,
                                'section' => 'A',
                                'max_students' => 30,
                                'status' => 'scheduled',
                            ]);
                            break; // Schedule one timeslot per semester for this course
                        }
                    }
                }
            }
        }
    }
}

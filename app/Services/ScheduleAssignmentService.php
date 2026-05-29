<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\Timeslot;
use Illuminate\Support\Facades\Log;

class ScheduleAssignmentService
{
    public function createManualAssignment(array $item, $semester): array
    {
        try {
            $courseOffering = CourseOffering::find($item['course_offering_id'] ?? null);
            if (! $courseOffering) {
                return ['success' => false, 'message' => 'Course offering not found'];
            }

            $section = Section::find($item['section_id'] ?? null);
            if (! $section) {
                return ['success' => false, 'message' => 'Section not found'];
            }

            if ($section->course_offering_id !== $courseOffering->id) {
                return ['success' => false, 'message' => 'Selected section does not belong to the course offering'];
            }

            $teacher = Teacher::find($item['teacher_id'] ?? null);
            if (! $teacher) {
                return ['success' => false, 'message' => 'Teacher not found'];
            }

            $room = Room::find($item['room_id'] ?? null);
            if (! $room) {
                return ['success' => false, 'message' => 'Room not found'];
            }

            $timeslot = Timeslot::find($item['timeslot_id'] ?? null);
            if (! $timeslot) {
                return ['success' => false, 'message' => 'Timeslot not found'];
            }

            if (! $section->teachers->contains($teacher)) {
                $section->teachers()->attach($teacher->id);
            }

            if ($this->hasTeacherConflict($teacher, $timeslot, $section->id)) {
                return ['success' => false, 'message' => 'The selected teacher has another class at this timeslot'];
            }

            if (Schedule::where('room_id', $room->id)->where('timeslot_id', $timeslot->id)->exists()) {
                return ['success' => false, 'message' => 'Room is already booked for this timeslot'];
            }

            if ($this->hasStudentConflict($section, $timeslot)) {
                return ['success' => false, 'message' => 'One or more students are already enrolled in another section at this timeslot'];
            }

            if ($room->capacity < $section->capacity) {
                return ['success' => false, 'message' => "Room capacity ({$room->capacity}) is insufficient for section size ({$section->capacity})"]; 
            }

            $course = $section->courseOffering->course;
            if (! $this->isRoomSuitableForCourse($room, $course)) {
                return ['success' => false, 'message' => 'Room type is not suitable for this course'];
            }

            Schedule::updateOrCreate(
                ['section_id' => $section->id, 'timeslot_id' => $timeslot->id],
                ['room_id' => $room->id]
            );

            return ['success' => true, 'message' => 'Schedule created successfully'];
        } catch (\Exception $e) {
            Log::error('ScheduleAssignmentService:createManualAssignment error', [
                'item' => $item,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['success' => false, 'message' => 'Error creating schedule: '.$e->getMessage()];
        }
    }

    protected function hasTeacherConflict(Teacher $teacher, Timeslot $timeslot, ?int $ignoreSectionId = null): bool
    {
        $query = Schedule::whereHas('section.teachers', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->where('timeslot_id', $timeslot->id);

        if ($ignoreSectionId) {
            $query->where('section_id', '!=', $ignoreSectionId);
        }

        return $query->exists();
    }

    protected function hasStudentConflict(Section $section, Timeslot $timeslot): bool
    {
        $studentIds = $section->enrollments->pluck('student_id');

        if ($studentIds->isEmpty()) {
            return false;
        }

        return Schedule::where('timeslot_id', $timeslot->id)
            ->whereHas('section.enrollments', function ($q) use ($studentIds) {
                $q->whereIn('student_id', $studentIds);
            })
            ->exists();
    }

    protected function isRoomSuitableForCourse(Room $room, Course $course): bool
    {
        if (! isset($room->type) || ! isset($course->course_type)) {
            return true;
        }

        return $room->type === $course->course_type;
    }
}

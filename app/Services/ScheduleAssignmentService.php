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

            $teacherWeeklyLoad = Schedule::whereHas('section.teachers', function ($q) use ($teacher) {
                $q->where('teachers.id', $teacher->id);
            })->whereHas('section.courseOffering', function ($q) use ($semester) {
                $q->where('semester_id', $semester->id);
            })->count();

            $maxHours = min(
                (int) ($teacher->max_hours_per_week ?? config('scheduling.max_teacher_hours_per_week', 38)),
                (int) config('scheduling.max_teacher_hours_per_week', 38)
            );

            if ($teacherWeeklyLoad >= $maxHours) {
                return ['success' => false, 'message' => 'Teacher has reached the maximum weekly load limit'];
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

            if (! $this->canSectionUseRoom($section, $room)) {
                return ['success' => false, 'message' => 'Room cannot be shared with this section under the current scheduling rules'];
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

    protected function canSectionUseRoom(Section $section, Room $room): bool
    {
        $scheduledSections = Schedule::where('room_id', $room->id)
            ->with(['section.courseOffering.course'])
            ->get()
            ->pluck('section')
            ->filter()
            ->unique('id')
            ->values();

        if ($scheduledSections->contains('id', $section->id)) {
            return true;
        }

        if ($scheduledSections->count() >= (int) config('scheduling.room_max_sections', 2)) {
            return false;
        }

        if ($scheduledSections->isEmpty()) {
            return true;
        }

        $existingSection = $scheduledSections->first();
        if (! $existingSection) {
            return false;
        }

        if (! $this->isSectionRoomPairCompatible($section, $existingSection)) {
            return false;
        }

        $combinedHours = $this->getSectionWeeklyHours($section) + $this->getSectionWeeklyHours($existingSection);

        return $combinedHours <= (int) config('scheduling.room_combined_hours_limit', 38);
    }

    protected function getSectionWeeklyHours(Section $section): int
    {
        return max(1, (int) ($section->courseOffering->course->credits ?? $section->courseOffering->course->hours_per_week ?? 3));
    }

    protected function getSectionDepartmentId(Section $section): ?int
    {
        return $section->courseOffering?->course?->department_id ? (int) $section->courseOffering->course->department_id : null;
    }

    protected function getSectionBatchKey(Section $section): string
    {
        return (string) ($section->courseOffering?->semester_id ?? '');
    }

    protected function isAdjacentBatch($batchA, $batchB): bool
    {
        if (! is_numeric($batchA) || ! is_numeric($batchB)) {
            return false;
        }

        return abs((int) $batchA - (int) $batchB) === 1;
    }

    protected function isSectionRoomPairCompatible(Section $sectionA, Section $sectionB): bool
    {
        $deptA = $this->getSectionDepartmentId($sectionA);
        $deptB = $this->getSectionDepartmentId($sectionB);
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
}

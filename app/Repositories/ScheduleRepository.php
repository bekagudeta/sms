<?php

namespace App\Repositories;

use App\Models\Schedule;
use App\Support\TeacherScope;
use Illuminate\Support\Collection;

class ScheduleRepository
{
    public function getAll(): Collection
    {
        return Schedule::with($this->scheduleRelations())
            ->get();
    }

    public function paginate(int $perPage = 10, ?int $semesterId = null)
    {
        return Schedule::with($this->scheduleRelations())
            ->when($semesterId, fn($query) => $query->whereHas('section.courseOffering', function ($q) use ($semesterId) {
                $q->where('semester_id', $semesterId);
            }))
            ->join('timeslots', 'schedules.timeslot_id', '=', 'timeslots.id')
            ->orderBy('timeslots.day_of_week')
            ->orderBy('timeslots.start_time')
            ->select('schedules.*')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateByTeacher(int $teacherId, int $perPage = 10)
    {
        return Schedule::with($this->scheduleRelations())
            ->whereHas('section.teachers', function ($q) use ($teacherId) {
                TeacherScope::wherePrimaryKey($q, $teacherId);
            })
            ->join('timeslots', 'schedules.timeslot_id', '=', 'timeslots.id')
            ->orderBy('timeslots.day_of_week')
            ->orderBy('timeslots.start_time')
            ->select('schedules.*')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateBySemester(int $semesterId, int $perPage = 10)
    {
        return Schedule::with($this->scheduleRelations())
            ->whereHas('section.courseOffering', function ($q) use ($semesterId) {
                $q->where('semester_id', $semesterId);
            })
            ->join('timeslots', 'schedules.timeslot_id', '=', 'timeslots.id')
            ->orderBy('timeslots.day_of_week')
            ->orderBy('timeslots.start_time')
            ->select('schedules.*')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateByStudent(int $studentId, int $perPage = 10)
    {
        return Schedule::with($this->scheduleRelations())
            ->whereHas('section.enrollments', function ($q) use ($studentId) {
                $q->where('student_id', $studentId);
            })
            ->join('timeslots', 'schedules.timeslot_id', '=', 'timeslots.id')
            ->orderBy('timeslots.day_of_week')
            ->orderBy('timeslots.start_time')
            ->select('schedules.*')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findById($id): ?Schedule
    {
        return Schedule::with($this->scheduleRelations())
            ->find($id);
    }

    public function create(array $data): Schedule
    {
        return Schedule::create($data);
    }

    public function update($id, array $data): bool
    {
        $schedule = $this->findById($id);
        return $schedule ? $schedule->update($data) : false;
    }

    public function delete($id): bool
    {
        $schedule = $this->findById($id);
        return $schedule ? $schedule->delete() : false;
    }

    public function getBySemester($semesterId): Collection
    {
        return Schedule::whereHas('section.courseOffering', function ($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        })
            ->with($this->scheduleRelations())
            ->get();
    }

    public function getByTeacher($teacherId): Collection
    {
        return Schedule::whereHas('section.teachers', function ($q) use ($teacherId) {
            TeacherScope::wherePrimaryKey($q, $teacherId);
        })
            ->with($this->scheduleRelations())
            ->get();
    }

    public function getByRoom($roomId): Collection
    {
        return Schedule::where('room_id', $roomId)
            ->with($this->scheduleRelations())
            ->get();
    }

    protected function scheduleRelations(): array
    {
        return [
            'section.courseOffering.course.department',
            'section.courseOffering.semester',
            'section.teachers.user',
            'section.enrollments.student',
            'room',
            'timeslot',
        ];
    }

    public function checkConflicts($roomId, $timeslotId, $semesterId, $excludeId = null): bool
    {
        $query = Schedule::where('room_id', $roomId)
            ->where('timeslot_id', $timeslotId)
            ->whereHas('section.courseOffering', function ($q) use ($semesterId) {
                $q->where('semester_id', $semesterId);
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}

<?php

namespace App\Repositories;

use App\Models\Course;
use Illuminate\Support\Collection;

class CourseRepository
{
    public function getAll(): Collection
    {
        return Course::with(['department', 'courseOfferings.sections.teachers.user'])->get();
    }

    public function paginate(int $perPage = 10, ?string $search = null)
    {
        return Course::with(['department', 'courseOfferings.sections.teachers.user'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('course_name', 'like', "%{$search}%")
                        ->orWhere('course_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findById($id): ?Course
    {
        return Course::with(['department', 'courseOfferings.sections.teachers.user'])->find($id);
    }

    public function create(array $data): Course
    {
        return Course::create($data);
    }

    public function update($id, array $data): bool
    {
        $course = Course::find($id);
        return $course ? $course->update($data) : false;
    }

    public function delete($id): bool
    {
        $course = $this->findById($id);
        return $course ? $course->delete() : false;
    }

    public function getByDepartment($departmentId): Collection
    {
        return Course::where('department_id', $departmentId)
            ->with(['department', 'semester', 'teacher'])
            ->get();
    }

    public function getBySemester($semesterId): Collection
    {
        return Course::whereHas('courseOfferings', function ($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        })
            ->with(['department', 'courseOfferings.sections.teachers.user'])
            ->get();
    }

    public function getByTeacher($teacherId): Collection
    {
        return Course::whereHas('courseOfferings.sections.teachers', function ($q) use ($teacherId) {
            $q->where('teacher_id', $teacherId);
        })
            ->with(['department', 'courseOfferings.sections.teachers.user'])
            ->get();
    }
}

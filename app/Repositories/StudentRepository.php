<?php

namespace App\Repositories;

use App\Models\Student;
use Illuminate\Support\Collection;

class StudentRepository
{
    public function getAll(): Collection
    {
        return Student::with('department')->get();
    }

    public function paginate(int $perPage = 10, ?string $search = null)
    {
        return Student::with('department')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findById($id): ?Student
    {
        return Student::with('department')->find($id);
    }

    public function findByStudentId($studentId): ?Student
    {
        return Student::where('student_id', $studentId)->first();
    }

    public function create(array $data): Student
    {
        return Student::create($data);
    }

    public function update($id, array $data): bool
    {
        $student = $this->findById($id);
        return $student ? $student->update($data) : false;
    }
    
    public function delete($id): bool
    {
        $student = $this->findById($id);

        return $student ? $student->delete() : false;
    }

    public function getByDepartment($departmentId): Collection
    {
        return Student::where('department_id', $departmentId)
            ->with('department')
            ->get();
    }

    public function getBySemester($semester): Collection
    {
        return Student::where('semester', $semester)
            ->with('department')
            ->get();
    }
}

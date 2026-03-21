<?php

namespace App\Imports;

use App\Models\Course;
use App\Models\Department;
use App\Models\Semester;
use App\Models\Teacher;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Collection;

class CoursesImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected $rowCount = 0;

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $department = Department::firstOrCreate(
                ['code' => $row['department_code'] ?? 'UNK'],
                ['name' => $row['department_name'] ?? 'Unknown Department']
            );
            $semester = Semester::firstOrCreate(
                ['code' => $row['semester_code'] ?? 'UNK'],
                ['name' => $row['semester_name'] ?? 'Unknown Semester', 'start_date' => now(), 'end_date' => now()->addMonths(4)]
            );
            $teacher = Teacher::where('teacher_id', $row['teacher_id'] ?? '')->first();

            Course::updateOrCreate(
                ['course_code' => $row['course_code'] ?? null],
                [
                    'course_name' => $row['course_name'] ?? '',
                    'description' => $row['description'] ?? null,
                    'credits' => $row['credits'] ?? 3,
                    'hours_per_week' => $row['hours_per_week'] ?? 3,
                    'department_id' => $department->id,
                    'semester_id' => $semester->id,
                    'teacher_id' => $teacher->id ?? null,
                    'level' => $row['level'] ?? 'undergraduate'
                ]
            );
            
            $this->rowCount++;
        }
    }

    public function rules(): array
    {
        return [
            'course_code' => 'required|string',
            'course_name' => 'required|string',
            'credits' => 'required|integer|min:1|max:6',
            'hours_per_week' => 'required|integer|min:1|max:6',
            'department_code' => 'required|string|exists:departments,code',
            'semester_code' => 'required|string|exists:semesters,code',
            'level' => 'required|in:undergraduate,graduate,diploma'
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
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

            // Skip empty rows
            if (empty($row['course_code'])) {
                continue;
            }

            // Handle department_id - find existing department
            $department = null;
            if (!empty($row['department_id'])) {
                $department = Department::find($row['department_id']);
            }

            // Handle semester_id - find existing semester
            $semester = null;
            if (!empty($row['semester_id'])) {
                $semester = Semester::find($row['semester_id']);
            }

            // Only import if both department and semester exist
            if (!$department || !$semester) {
                continue; // Skip this row if department or semester doesn't exist
            }

            $teacher = null;
            if (!empty($row['teacher_id'])) {
                $teacher = Teacher::where('teacher_id', $row['teacher_id'])->first();
            }

            Course::updateOrCreate(
                ['course_code' => $row['course_code']],
                [
                    'course_name' => $row['course_name'],
                    'description' => $row['description'] ?? null,
                    'credits' => $row['credits'] ?? 3,
                    'hours_per_week' => $row['hours_per_week'] ?? 3,
                    'department_id' => $department->id,
                    'semester_id' => $semester->id,
                    'teacher_id' => $teacher ? $teacher->id : null,
                    'level' => $row['level'] ?? 'undergraduate'
                ]
            );

            $this->rowCount++;
        }
    }

    public function rules(): array
    {
        return [
            '*.course_code' => 'required|string',
            '*.course_name' => 'required|string',
            '*.credits' => 'required|integer|min:1|max:6',
            '*.hours_per_week' => 'required|integer|min:1|max:6',
            '*.department_id' => 'required|integer',
            '*.semester_id' => 'required|integer',
            '*.level' => 'required|in:undergraduate,graduate,diploma'
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
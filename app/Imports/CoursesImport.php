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

            // Normalize department lookup: can be numeric id or department code string
            $departmentValue = trim((string) ($row['department_id'] ?? ''));
            $department = null;

            if ($departmentValue !== '') {
                if (ctype_digit($departmentValue)) {
                    $department = Department::find((int) $departmentValue);
                }

                if (! $department) {
                    $department = Department::where('code', $departmentValue)->first();
                }
            }

            // If department is still missing, create it using department_name or default name
            if (! $department) {
                $departmentName = trim((string) ($row['department_name'] ?? $departmentValue));
                $department = Department::firstOrCreate(
                    ['code' => $departmentValue],
                    ['name' => $departmentName]
                );
            }

            // If still no department (shouldn't happen), skip
            if (! $department) {
                continue;
            }

$rawLevel = trim((string) ($row['level'] ?? 'undergraduate'));
            $normalizedLevel = strtolower($rawLevel);

            $levelMap = [
                'undergraduate' => 'undergraduate',
                'undergrad' => 'undergraduate',
                'ug' => 'undergraduate',
                'graduate' => 'graduate',
                'grad' => 'graduate',
                'g' => 'graduate',
                'diploma' => 'diploma',
                'dip' => 'diploma',
                'd' => 'diploma',
            ];

            $level = $levelMap[$normalizedLevel] ?? null;

            if (! $level) {
                // Fallback to default so import doesn't break on case mismatch
                $level = 'undergraduate';
            }

            $rawRoomType = trim((string) ($row['required_room_type'] ?? 'lecture'));
            $normalizedRoomType = strtolower($rawRoomType);

            $roomTypeMap = [
                'lecture' => 'lecture',
                'lab' => 'lab',
                'laboratory' => 'lab',
                'classroom' => 'lecture',
                'hall' => 'lecture',
                'auditorium' => 'lecture',
            ];

            $roomType = $roomTypeMap[$normalizedRoomType] ?? 'lecture';

            Course::updateOrCreate(
                ['course_code' => $row['course_code']],
                [
                    'course_name' => $row['course_name'],
                    'description' => $row['description'] ?? null,
                    'credits' => $row['credits'] ?? 3,
                    'hours_per_week' => $row['hours_per_week'] ?? 3,
                    'department_id' => $department->id,
                    'level' => $level,
                    'required_room_type' => $roomType
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
            '*.department_id' => 'required',
            '*.department_name' => 'nullable|string',
            '*.level' => 'required|string',
            '*.required_room_type' => 'nullable|string'
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
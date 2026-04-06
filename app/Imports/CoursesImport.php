<?php

namespace App\Imports;

use App\Models\Course;
use App\Models\Department;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CoursesImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected int $rowCount = 0;
    protected array $errors = [];

    protected $departments;

    public function __construct()
    {
        // 🔥 preload departments
        $this->departments = Department::all()->keyBy('code');
    }

    public function collection(Collection $rows)
    {
        $batch = [];

        foreach ($rows as $index => $row) {

            if (empty($row['course_code'])) {
                continue;
            }

            $rowNumber = $index + 2;

            $courseCode = strtolower(trim($row['course_code']));
            $departmentCode = strtolower(trim((string) ($row['department_id'] ?? '')));

            if (!$departmentCode) {
                $this->errors[] = "Row {$rowNumber}: Missing department code";
                continue;
            }

            $department = $this->departments[$departmentCode] ?? null;

            if (!$department) {
                $this->errors[] = "Row {$rowNumber}: Department not found ({$departmentCode})";
                continue;
            }

            $level = $this->normalizeLevel($row['level'] ?? null, $rowNumber);
            $roomType = $this->normalizeRoomType($row['required_room_type'] ?? null);

            $batch[] = [
                'course_code' => $courseCode,
                'course_name' => trim($row['course_name']),
                'description' => $row['description'] ?? null,
                'credits' => (int) ($row['credits'] ?? 3),
                'hours_per_week' => (int) ($row['hours_per_week'] ?? 3),
                'department_id' => $department->id,
                'level' => $level,
                'required_room_type' => $roomType,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $this->rowCount++;

            if (count($batch) >= 500) {
                $this->upsertBatch($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->upsertBatch($batch);
        }
    }

    protected function upsertBatch(array $batch)
    {
        DB::table('courses')->upsert(
            $batch,
            ['course_code'],
            [
                'course_name',
                'description',
                'credits',
                'hours_per_week',
                'department_id',
                'level',
                'required_room_type',
                'updated_at'
            ]
        );
    }

    protected function normalizeLevel($value, $rowNumber)
    {
        $map = [
            'undergraduate' => 'undergraduate',
            'undergrad' => 'undergraduate',
            'ug' => 'undergraduate',
            'graduate' => 'graduate',
            'grad' => 'graduate',
            'g' => 'graduate',
            'diploma' => 'diploma',
        ];

        $key = strtolower(trim((string) $value));

        if (!isset($map[$key])) {
            $this->errors[] = "Row {$rowNumber}: Invalid level '{$value}'";
            return 'undergraduate';
        }

        return $map[$key];
    }

    protected function normalizeRoomType($value)
    {
        $map = [
            'lecture' => 'lecture',
            'lab' => 'lab',
            'laboratory' => 'lab',
            'classroom' => 'lecture',
        ];

        return $map[strtolower(trim((string) $value))] ?? 'lecture';
    }

    public function rules(): array
    {
        return [
            '*.course_code' => 'required|string',
            '*.course_name' => 'required|string',
            '*.credits' => 'required|integer|min:1|max:6',
            '*.hours_per_week' => 'required|integer|min:1|max:6',
            '*.department_id' => 'required|string',
            '*.level' => 'required|string',
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
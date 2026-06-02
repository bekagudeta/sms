<?php

namespace App\Imports;

use App\Models\Course;
use App\Models\Department;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CoursesImport implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    protected int $rowCount = 0;
    protected array $errors = [];

    protected array $departmentsById = [];
    protected array $departmentsByCode = [];
    protected array $departmentsByName = [];

    public function __construct()
    {
        $departments = Department::all();

        foreach ($departments as $department) {
            $this->departmentsById[$department->id] = $department;
            $this->departmentsByCode[strtolower(trim((string) $department->code))] = $department;
            $this->departmentsByName[strtolower(trim((string) $department->name))] = $department;
        }
    }

    public function collection(Collection $rows)
    {
        $batch = [];

        foreach ($rows as $index => $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $rowNumber = $index + 2;
            $courseCode = strtolower(trim((string) ($row['course_code'] ?? '')));

            if ($courseCode === '') {
                $this->errors[] = "Row {$rowNumber}: The course_code field is required.";
                continue;
            }

            $departmentId = $this->resolveDepartmentId($row, $rowNumber);
            if ($departmentId === null) {
                continue;
            }

            $level = $this->normalizeLevel($row['level'] ?? null, $rowNumber);
            $roomType = $this->normalizeRoomType($row['required_room_type'] ?? null);

            $batch[] = [
                'course_code' => $courseCode,
                'course_name' => trim((string) ($row['course_name'] ?? '')),
                'description' => $row['description'] ?? null,
                'credits' => (int) ($row['credits'] ?? 3),
                'hours_per_week' => (int) ($row['hours_per_week'] ?? 3),
                'department_id' => $departmentId,
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
            '*.credits' => 'required|integer|min:1|max:38',
            '*.hours_per_week' => 'required|integer|min:1|max:38',
            '*.department_id' => 'required_without_all:*.department_code,*.department_name|integer|exists:departments,id',
            '*.department_code' => 'nullable|string|exists:departments,code',
            '*.department_name' => 'nullable|string|exists:departments,name',
            '*.level' => 'required|string',
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    protected function isBlankRow($row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function resolveDepartmentId($row, int $rowNumber): ?int
    {
        $departmentId = trim((string) ($row['department_id'] ?? ''));
        if ($departmentId !== '') {
            if (is_numeric($departmentId) && isset($this->departmentsById[(int) $departmentId])) {
                return (int) $departmentId;
            }

            $this->errors[] = "Row {$rowNumber}: Department not found for department_id '{$departmentId}'.";
            return null;
        }

        $departmentCode = strtolower(trim((string) ($row['department_code'] ?? '')));
        if ($departmentCode !== '') {
            $department = $this->departmentsByCode[$departmentCode] ?? null;
            if ($department) {
                return $department->id;
            }

            $this->errors[] = "Row {$rowNumber}: Department not found for department_code '{$row['department_code']}'.";
            return null;
        }

        $departmentName = strtolower(trim((string) ($row['department_name'] ?? '')));
        if ($departmentName !== '') {
            $department = $this->departmentsByName[$departmentName] ?? null;
            if ($department) {
                return $department->id;
            }

            $this->errors[] = "Row {$rowNumber}: Department not found for department_name '{$row['department_name']}'.";
            return null;
        }

        $this->errors[] = "Row {$rowNumber}: Missing department_id, department_code or department_name.";
        return null;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

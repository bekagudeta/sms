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
            // Department may arrive as id, code, or name. resolveDepartmentId()
            // validates and resolves it (case-insensitive), so keep these lenient
            // here — a department code in any of these columns must not be rejected
            // as a non-integer before we get a chance to resolve it.
            '*.department_id' => 'nullable',
            '*.department_code' => 'nullable|string',
            '*.department_name' => 'nullable|string',
            '*.level' => 'nullable|string',
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
        $rawId = trim((string) ($row['department_id'] ?? ''));

        // A numeric department_id is treated as a real primary key.
        if ($rawId !== '' && is_numeric($rawId)) {
            if (isset($this->departmentsById[(int) $rawId])) {
                return (int) $rawId;
            }

            $this->errors[] = "Row {$rowNumber}: Department not found for department_id '{$rawId}'.";
            return null;
        }

        // Otherwise resolve by code, then name (case-insensitive). A non-numeric
        // department_id (e.g. a code mistakenly placed there) is treated as a
        // code/name candidate so the import still succeeds.
        $code = strtolower(trim((string) ($row['department_code'] ?? '')));
        if ($code === '' && $rawId !== '') {
            $code = strtolower($rawId);
        }
        if ($code !== '' && isset($this->departmentsByCode[$code])) {
            return $this->departmentsByCode[$code]->id;
        }

        $name = strtolower(trim((string) ($row['department_name'] ?? '')));
        if ($name === '' && $rawId !== '') {
            $name = strtolower($rawId);
        }
        if ($name !== '' && isset($this->departmentsByName[$name])) {
            return $this->departmentsByName[$name]->id;
        }

        $provided = trim((string) ($row['department_code'] ?? $row['department_name'] ?? $rawId));
        if ($provided !== '') {
            $this->errors[] = "Row {$rowNumber}: Department not found for '{$provided}'.";
        } else {
            $this->errors[] = "Row {$rowNumber}: Missing department_id, department_code or department_name.";
        }

        return null;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

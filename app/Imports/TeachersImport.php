<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Spatie\Permission\Models\Role;

class TeachersImport implements ToCollection, WithHeadingRow, WithValidation
{
    public $credentials = [];

    protected $rowCount = 0;

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $department = $this->resolveDepartment($row);

            // =========================
            // 1. User handling
            // =========================
            $user = User::where('email', $row['email'])->first();
            $plainPassword = null;

            if ($user) {
                if (empty($user->password)) {
                    $plainPassword = Str::random(12);
                    $user->update([
                        'password' => Hash::make($plainPassword, ['rounds' => 8]),
                        'must_change_password' => true,
                    ]);
                }
            } else {
                $plainPassword = Str::random(12);

                $user = User::create([
                    'name' => trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')),
                    'email' => $row['email'],
                    'password' => Hash::make($plainPassword, ['rounds' => 8]),
                    'must_change_password' => true,
                ]);
            }

            if ($plainPassword) {
                $this->credentials[] = [
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $plainPassword,
                ];
            }

            // Ensure the role exists and is assigned
            Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);

            if (! $user->hasRole('teacher')) {
                $user->assignRole('teacher');
            }

            // Create or update teacher profile
            Teacher::updateOrCreate(
                ['teacher_id' => $row['teacher_id'] ?? null],
                [
                    'user_id' => $user->id,
                    'first_name' => $row['first_name'] ?? '',
                    'last_name' => $row['last_name'] ?? '',
                    'email' => $row['email'] ?? '',
                    'phone' => $row['phone'] ?? null,
                    'department_id' => $department->id,
                    'qualification' => $row['qualification'] ?? null,
                    'max_hours_per_week' => $row['max_hours_per_week'] ?? 38,
                ]
            );
            $this->rowCount++;
        }
    }

    public function rules(): array
    {
        return [
            '*.teacher_id' => 'required|string',
            '*.first_name' => 'required|string',
            '*.last_name' => 'required|string',
            '*.email' => 'required|email',
            '*.department_id' => 'required_without_all:*.department_code,*.department_name|string',
            '*.department_code' => 'nullable|string|exists:departments,code',
            '*.department_name' => 'nullable|string|exists:departments,name',
            '*.phone' => 'nullable|string',
            '*.qualification' => 'nullable|string',
            '*.max_hours_per_week' => 'nullable|integer|min:1|max:38',
        ];
    }

    protected function resolveDepartment($row)
    {
        if ($row instanceof Collection) {
            $row = $row->toArray();
        }

        $departmentId = trim((string) ($row['department_id'] ?? ''));
        $departmentCode = trim((string) ($row['department_code'] ?? ''));
        $departmentName = trim((string) ($row['department_name'] ?? ''));

        if ($departmentId !== '') {
            if (ctype_digit($departmentId)) {
                $department = Department::find((int) $departmentId);
            } else {
                $department = Department::whereRaw('LOWER(code) = ?', [Str::lower($departmentId)])->first();
            }

            if (! $department) {
                throw new \Exception("Import failed: department_id '{$departmentId}' not found for teacher row.");
            }

            return $department;
        }

        if ($departmentCode !== '') {
            $department = Department::whereRaw('LOWER(code) = ?', [Str::lower($departmentCode)])->first();
            if (! $department) {
                throw new \Exception("Import failed: department_code '{$departmentCode}' not found for teacher row.");
            }

            return $department;
        }

        if ($departmentName !== '') {
            $department = Department::whereRaw('LOWER(name) = ?', [Str::lower($departmentName)])->first();
            if (! $department) {
                throw new \Exception("Import failed: department_name '{$departmentName}' not found for teacher row.");
            }

            return $department;
        }

        throw new \Exception('Import failed: department_id, department_code, or department_name is required for teacher row.');
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}

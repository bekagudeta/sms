<?php

namespace App\Imports;

use App\Models\Teacher;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class TeachersImport implements ToCollection, WithHeadingRow, WithValidation
{
    public $credentials = [];
    protected $rowCount = 0;

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $departmentInput = trim((string) ($row['department_id'] ?? ''));
            $departmentName = trim((string) ($row['department_name'] ?? ''));

            if ($departmentInput === '') {
                throw new \Exception('Import failed: department_id is required for teacher row.');
            }

            // 1) try numeric db id
            if (ctype_digit($departmentInput)) {
                $department = Department::find((int) $departmentInput);
            } else {
                $department = null;
            }

            // 2) if no numeric department, treat as code string
            if (! $department) {
                $department = Department::where('code', $departmentInput)->first();
            }

            // 3) If still not found and department_name is provided, create it
            if (! $department && $departmentName !== '') {
                $department = Department::create([
                    'code' => $departmentInput,
                    'name' => $departmentName,
                ]);
            }

            // 4) final fallback is not allowed (system depends on department relation)
            if (! $department) {
                throw new \Exception("Import failed: department '$departmentInput' not found, and department_name is missing.");
            }

            // =========================
            // 1. User handling
            // =========================
            $user = User::where('email', $row['email'])->first();
            $plainPassword = null;

            if ($user) {
                if (empty($user->plain_password)) {
                    $plainPassword = Str::random(8);
                    $user->update([
                        'password' => Hash::make($plainPassword, ['rounds' => 8]),
                        'plain_password' => $plainPassword,
                        'must_change_password' => true,
                    ]);
                } else {
                    $plainPassword = $user->plain_password;
                    if (empty($user->password)) {
                        $user->update(['password' => Hash::make($plainPassword, ['rounds' => 8])]);
                    }
                }
            } else {
                $plainPassword = Str::random(8);

                $user = User::create([
                    'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                    'email' => $row['email'],
                    'password' => Hash::make($plainPassword, ['rounds' => 8]),
                    'plain_password' => $plainPassword,
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
                    'max_hours_per_week' => $row['max_hours_per_week'] ?? 20
                ]
            );
            $this->rowCount++;
        }
    }

    public function rules(): array
    {
        return [
            'teacher_id' => 'required|string',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email',
            'department_id' => 'required|string',
            'department_name' => 'nullable|string',
            'phone' => 'nullable|string',
            'qualification' => 'nullable|string',
            'max_hours_per_week' => 'nullable|integer|min:1|max:40'
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
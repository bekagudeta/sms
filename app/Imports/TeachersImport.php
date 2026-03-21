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
            $department = Department::firstOrCreate(
                ['code' => $row['department_code'] ?? 'UNK'],
                ['name' => $row['department_name'] ?? 'Unknown Department']
            );

            // =========================
            // 1. User handling
            // =========================
            $user = User::where('email', $row['email'])->first();
            $plainPassword = null;

            if ($user) {
                // Ensure we have a plain password to export, and force a password reset if not already required.
                if (! $user->must_change_password || ! $user->plain_password) {
                    $plainPassword = Str::random(8);
                    $user->update([
                        'password' => Hash::make($plainPassword, ['rounds' => 8]),
                        'must_change_password' => true,
                        'plain_password' => $plainPassword,
                    ]);
                } else {
                    $plainPassword = $user->plain_password;
                }
            } else {
                $plainPassword = Str::random(8);

                $user = User::create([
                    'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                    'email' => $row['email'],
                    'password' => Hash::make($plainPassword, ['rounds' => 8]),
                    'must_change_password' => true,
                    'plain_password' => $plainPassword,
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
            Role::findOrCreate('teacher', 'web');

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
            'department_code' => 'required|string',
            'department_name' => 'required|string',
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
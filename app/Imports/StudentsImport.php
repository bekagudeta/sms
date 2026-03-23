<?php

namespace App\Imports;

use App\Models\Student;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Spatie\Permission\Models\Role;
class StudentsImport implements 
    ToCollection,
    WithHeadingRow,
    WithValidation,
    WithChunkReading
{
    public $credentials = [];
    protected $rowCount = 0;

    public function chunkSize(): int
    {
        return 100;
    }

    public function collection(Collection $rows)
    {
        DB::disableQueryLog();
        DB::beginTransaction();

        try {

            DB::disableQueryLog();

            // 🔥 preload departments (avoid repeated queries)
            $departmentCache = [];

            foreach ($rows as $row) {

                // =========================
                // 1. Department handling
                // =========================
                $deptCode = $row['department_code'] ?? 'UNK';

                if (!isset($departmentCache[$deptCode])) {

                    $departmentCache[$deptCode] = Department::firstOrCreate(
                        ['code' => $deptCode],
                        ['name' => $row['department_name'] ?? 'Unknown']
                    );
                }

                $department = $departmentCache[$deptCode];

                // =========================
                // 2. User handling (SAFE)
                // =========================
                $user = User::where('email', $row['email'])->first();

                $plainPassword = null;

                if ($user) {
                    // Student exists; if we don't have plain password, generate new secure token
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
                            $user->update([ 'password' => Hash::make($plainPassword, ['rounds' => 8]) ]);
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
                Role::findOrCreate('student', 'web');
                if (!$user->hasRole('student')) {
                    $user->assignRole('student');
                }

                // =========================
                // 3. Student profile
                // =========================
                Student::updateOrCreate(
                    ['student_id' => $row['student_id']],
                    [
                        'user_id' => $user->id,
                        'first_name' => $row['first_name'],
                        'last_name' => $row['last_name'],
                        'email' => $row['email'],
                        'phone' => $row['phone'] ?? null,
                        'department_id' => $department->id,
                        'semester' => $row['semester'] ?? 1,
                        'level' => $row['level'] ?? null,
                        'section' => $row['section'] ?? null,
                        'enrollment_date' => $row['enrollment_date'] ?? now()
                    ]
                );

                $this->rowCount++;
            }

            DB::commit();

        } catch (\Exception $e) {

            DB::rollBack();
            throw $e;
        }
    }

    // =========================
    // Validation rules
    // =========================
    public function rules(): array
    {
        return [
            '*.student_id' => 'required|string',
            '*.first_name' => 'required|string',
            '*.last_name' => 'required|string',
            '*.email' => 'required|email',
            '*.department_code' => 'required|string',
            '*.department_name' => 'required|string',
            '*.semester' => 'nullable|integer',
            '*.enrollment_date' => 'nullable|date',
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
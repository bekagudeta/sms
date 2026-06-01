<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Spatie\Permission\Models\Role;

class StudentsImport implements ToCollection, WithChunkReading, WithHeadingRow, WithValidation
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
            foreach ($rows as $row) {
                $department = $this->resolveDepartment($row);

                $user = User::where('email', $row['email'])->first();

                $plainPassword = null;

                if ($user) {
                    if (empty($user->password)) {
                        $plainPassword = Str::random(12);
                        $user->update([
                            'password' => Hash::make($plainPassword, ['rounds' => 8]),
                            'plain_password' => '',
                            'must_change_password' => true,
                        ]);
                    }
                } else {
                    $plainPassword = Str::random(12);

                    $user = User::create([
                        'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                        'email' => $row['email'],
                        'password' => Hash::make($plainPassword, ['rounds' => 8]),
                        'plain_password' => '',
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

                Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
                if (!$user->hasRole('student')) {
                    $user->assignRole('student');
                }

                $enrollmentDate = $this->parseOptionalDate(
                    $row['enrollment_date'] ?? $row['date_of_birth'] ?? null
                ) ?? now();

                Student::updateOrCreate(
                    ['student_id' => $row['student_id']],
                    [
                        'user_id'         => $user->id,
                        'first_name'      => trim($row['first_name'] ?? ''),
                        'last_name'       => trim($row['last_name'] ?? ''),
                        'email'           => trim($row['email'] ?? ''),
                        'phone'           => $row['phone'] ?? null,
                        'department_id'   => $department->id,
                        'status'          => $row['status'] ?? 'active',
                        'level'           => $this->optionalString($row['level'] ?? null),
                        'section'         => $this->optionalString($row['section'] ?? null) ?? '',
                        'enrollment_date' => $enrollmentDate,
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

    public function rules(): array
    {
        return [
            '*.student_id'      => 'required|string',
            '*.first_name'      => 'required|string',
            '*.last_name'       => 'required|string',
            '*.email'           => 'required|email',
            '*.department_id'   => 'nullable',
            '*.department_code' => 'required_without:*.department_id|nullable|string',
            '*.department_name' => 'nullable|string',
            '*.level'           => 'nullable|string',
            '*.section'         => 'required|string',
            '*.phone'           => 'nullable|string',
            // Removed strict date validation — handled manually in parseOptionalDate()
            // because Excel stores dates as numeric serial numbers
            '*.enrollment_date' => 'nullable',
            '*.date_of_birth'   => 'nullable',
            '*.status'          => 'nullable|string',
        ];
    }

    protected function resolveDepartment($row): Department
    {
        if ($row instanceof Collection) {
            $row = $row->toArray();
        }

        $departmentId   = trim((string) ($row['department_id'] ?? ''));
        $departmentCode = trim((string) ($row['department_code'] ?? ''));
        $departmentName = trim((string) ($row['department_name'] ?? ''));

        if ($departmentId !== '') {
            if (ctype_digit($departmentId)) {
                $department = Department::find((int) $departmentId);
            } else {
                $department = Department::whereRaw('LOWER(code) = ?', [Str::lower($departmentId)])->first();
            }

            if (!$department) {
                throw new \Exception("Import failed: department_id '{$departmentId}' not found for student {$row['student_id']}.");
            }

            return $department;
        }

        if ($departmentCode !== '') {
            $department = Department::whereRaw('LOWER(code) = ?', [Str::lower($departmentCode)])->first();
            if (!$department) {
                throw new \Exception(
                    "Import failed: department_code '{$departmentCode}' not found for student {$row['student_id']}. Import departments first; codes must match exactly (case-insensitive)."
                );
            }

            return $department;
        }

        if ($departmentName !== '') {
            $department = Department::whereRaw('LOWER(name) = ?', [Str::lower($departmentName)])->first();
            if (!$department) {
                throw new \Exception("Import failed: department_name '{$departmentName}' not found for student {$row['student_id']}.");
            }

            return $department;
        }

        throw new \Exception(
            "Import failed: department_id, department_code, or department_name is required for student {$row['student_id']}."
        );
    }

    /**
     * Parse a date value that may be:
     * - An Excel numeric serial number (e.g. 37756)
     * - A string date (e.g. "2003-05-15", "5/15/2003")
     * - Already a Carbon instance
     * - null or empty
     */
    protected function parseOptionalDate(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        // Handle string values
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
            try {
                return Carbon::parse($value);
            } catch (\Exception $e) {
                return null;
            }
        }

        // Handle Excel numeric serial date (e.g. 37756.0)
        if (is_numeric($value)) {
            try {
                $dateTime = ExcelDate::excelToDateTimeObject((float) $value);
                return Carbon::instance($dateTime);
            } catch (\Exception $e) {
                return null;
            }
        }

        // Handle if it's already a DateTime or Carbon instance
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        return null;
    }

    protected function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
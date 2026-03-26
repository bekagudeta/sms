<?php

namespace App\Imports;

use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Validators\Failure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EnrollmentsImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;
    protected $rowCount = 0;
    protected $errors = [];

    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                // Skip completely empty rows
                if (empty(array_filter($row->toArray()))) {
                    continue;
                }

                $rowNumber = $index + 2;

                // Check required fields
                $studentIdentifier = trim((string)($row['student_id'] ?? ''));
                $sectionIdentifier = trim((string)($row['section_id'] ?? ''));

                if ($studentIdentifier === '') {
                    $this->errors[] = "Row {$rowNumber}: student_id is required";
                    continue;
                }

                if ($sectionIdentifier === '') {
                    $this->errors[] = "Row {$rowNumber}: section_id or section_name is required";
                    continue;
                }

                // Student lookup: exact student_id first
                $student = Student::where('student_id', $studentIdentifier)
                    ->orWhereRaw('LOWER(student_id) = ?', [strtolower($studentIdentifier)])
                    ->first();

                if (!$student && preg_match('/^\d+$/', $studentIdentifier)) {
                    // If only numeric provided, try pattern with STU prefix + zero padded (common format)
                    $padded = str_pad($studentIdentifier, 4, '0', STR_PAD_LEFT);
                    $student = Student::where('student_id', 'STU' . $padded)
                        ->orWhere('student_id', 'STU' . ltrim($studentIdentifier, '0'))
                        ->first();

                    if (!$student) {
                        // Fallback to primary key as last resort
                        $student = Student::find((int)$studentIdentifier);
                    }
                }

                if (!$student && stripos($studentIdentifier, 'STU') !== 0 && preg_match('/^\d+$/', $studentIdentifier) === 0) {
                    // Sometimes STU may be omitted in user data; try adding it.
                    $student = Student::where('student_id', 'STU' . strtoupper($studentIdentifier))->first();
                }

                if (!$student && filter_var($studentIdentifier, FILTER_VALIDATE_EMAIL)) {
                    $student = Student::where('email', $studentIdentifier)->first();
                }

                if (!$student) {
                    $this->errors[] = "Row {$rowNumber}: Student '{$studentIdentifier}' not found";
                    continue;
                }

                // Section lookup: by numeric id first, then by section_name, then fallback using raw row values
                $section = null;

                if (is_numeric($sectionIdentifier) && (int)$sectionIdentifier > 0) {
                    $section = Section::find((int)$sectionIdentifier);
                }

                if (!$section) {
                    $section = Section::where('section_name', $sectionIdentifier)
                        ->orWhereRaw('LOWER(section_name) = ?', [strtolower($sectionIdentifier)])
                        ->first();
                }

                if (!$section && $sectionIdentifier === '0') {
                    $rawRow = $row->toArray();
                    // try to find a numeric section_id from another column if headings are off
                    foreach ($rawRow as $key => $value) {
                        if ($key === 'student_id') {
                            continue;
                        }
                        if (is_numeric($value) && (int)$value > 0) {
                            $section = Section::find((int)$value);
                            if ($section) {
                                $this->errors[] = "Row {$rowNumber}: section_id value was '0', using column '$key' with value '{$value}'";
                                break;
                            }
                        }
                    }
                }

                if (!$section) {
                    $this->errors[] = "Row {$rowNumber}: Section '{$sectionIdentifier}' not found";
                    continue;
                }

                // Check if enrollment already exists
                $existing = Enrollment::where('student_id', $student->id)
                    ->where('section_id', $section->id)
                    ->first();

                $studentCodeEnabled = \Schema::hasColumn('enrollments', 'student_code');

                if ($existing) {
                    if ($studentCodeEnabled) {
                        $existing->student_code = $student->student_id;
                    }
                    $existing->touch();
                } else {
                    $createData = [
                        'student_id' => $student->id,
                        'section_id' => $section->id,
                    ];

                    if ($studentCodeEnabled) {
                        $createData['student_code'] = $student->student_id;
                    }

                    Enrollment::create($createData);
                }

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
            '*.student_id' => 'required|string',
            '*.section_id' => 'required|string'
        ];
    }

    public function prepareForValidation($data, $index)
    {
        // Skip completely empty rows by returning null (will be filtered out)
        if (empty($data['student_id']) && empty($data['section_id'])) {
            return null;
        }

        // Normalize fields to string for consistent matching
        if (isset($data['student_id'])) {
            $data['student_id'] = trim((string)$data['student_id']);
        }

        if (isset($data['section_id'])) {
            $data['section_id'] = trim((string)$data['section_id']);
        }

        return $data;
    }

    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->errors[] = "Row {$failure->row()} [{$failure->attribute()}]: " . implode(' / ', $failure->errors());
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}

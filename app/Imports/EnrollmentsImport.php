<?php

namespace App\Imports;

use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

class EnrollmentsImport implements SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    protected $rowCount = 0;

    protected $errors = [];

    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $this->processRow($row, $index + 2);
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Enrollment import failed: '.$e->getMessage());
            throw $e;
        }
    }

    private function processRow($row, $rowNumber)
    {
        $rowData = $row->toArray();

        if (empty(array_filter($rowData))) {
            return;
        }

        $studentIdentifier = trim((string) ($rowData['student_id'] ?? ''));
        $sectionIdentifier = trim((string) ($rowData['section_id'] ?? ''));
        $enrolledAt = trim((string) ($rowData['enrolled_at'] ?? '')) ?: null;
        $studentCodeValue = trim((string) ($rowData['student_code_value'] ?? $rowData['student_code'] ?? '')) ?: null;

        if (! $studentIdentifier || ! $sectionIdentifier) {
            $this->errors[] = "Row {$rowNumber}: Both student_id and section_id are required";

            return;
        }

        $student = $this->findStudent($studentIdentifier, $rowNumber);
        if (! $student) {
            return;
        }

        $section = $this->findExistingSection($sectionIdentifier, $rowNumber);
        if (! $section) {
            return;
        }

        $this->createOrUpdateEnrollment(
            $student->id,
            $section->id,
            $studentCodeValue ?? $student->student_id,
            $enrolledAt,
        );

        $this->rowCount++;
    }

    private function findStudent($identifier, $rowNumber)
    {
        $student = Student::where('student_id', $identifier)
            ->orWhereRaw('LOWER(student_id) = ?', [strtolower($identifier)])
            ->first();

        if (! $student && preg_match('/^\d+$/', $identifier)) {
            $padded = str_pad($identifier, 4, '0', STR_PAD_LEFT);
            $student = Student::where('student_id', 'STU'.$padded)
                ->orWhere('student_id', 'STU'.ltrim($identifier, '0'))
                ->first();

            if (! $student) {
                $student = Student::find((int) $identifier);
            }
        }

        if (! $student && stripos($identifier, 'STU') !== 0 && ! preg_match('/^\d+$/', $identifier)) {
            $student = Student::where('student_id', 'STU'.strtoupper($identifier))->first();
        }

        if (! $student && filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $student = Student::where('email', $identifier)->first();
        }

        if (! $student) {
            $this->errors[] = "Row {$rowNumber}: Student '{$identifier}' not found";
        }

        return $student;
    }

    private function findExistingSection($identifier, $rowNumber)
    {
        if (is_numeric($identifier)) {
            $section = Section::find((int) $identifier);
            if ($section) {
                return $section;
            }
        }

        $section = Section::where('section_name', $identifier)
            ->orWhereRaw('LOWER(section_name) = ?', [strtolower($identifier)])
            ->first();

        if ($section) {
            return $section;
        }

        $this->errors[] = "Row {$rowNumber}: Section '{$identifier}' not found. Import sections before enrollments.";

        return null;
    }

    private function createOrUpdateEnrollment($studentId, $sectionId, $studentCode = null, $enrolledAt = null)
    {
        $existing = Enrollment::where('student_id', $studentId)
            ->where('section_id', $sectionId)
            ->first();

        $hasStudentCode = Schema::hasColumn('enrollments', 'student_code');

        if ($existing) {
            if ($hasStudentCode && $studentCode) {
                $existing->student_code = $studentCode;
            }

            if ($enrolledAt) {
                $existing->enrolled_at = $enrolledAt;
            }

            $existing->touch();
            $existing->save();
        } else {
            $createData = [
                'student_id' => $studentId,
                'section_id' => $sectionId,
            ];

            if ($hasStudentCode && $studentCode) {
                $createData['student_code'] = $studentCode;
            }

            if ($enrolledAt) {
                $createData['enrolled_at'] = $enrolledAt;
            }

            Enrollment::create($createData);
        }
    }

    public function rules(): array
    {
        return [
            '*.student_id' => 'required|string',
            '*.section_id' => 'required|string',
            '*.enrolled_at' => 'nullable|date',
            '*.student_code_value' => 'nullable|string',
        ];
    }

    public function prepareForValidation($data, $index)
    {
        if (empty($data['student_id']) && empty($data['section_id'])) {
            return null;
        }

        if (isset($data['student_id'])) {
            $data['student_id'] = trim((string) $data['student_id']);
        }

        if (isset($data['section_id'])) {
            $data['section_id'] = trim((string) $data['section_id']);
        }

        if (isset($data['enrolled_at'])) {
            $data['enrolled_at'] = trim((string) $data['enrolled_at']);
        }

        if (isset($data['student_code_value'])) {
            $data['student_code_value'] = trim((string) $data['student_code_value']);
        }

        return $data;
    }

    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->errors[] = "Row {$failure->row()} [{$failure->attribute()}]: ".implode(' / ', $failure->errors());
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

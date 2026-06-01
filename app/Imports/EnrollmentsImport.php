<?php

namespace App\Imports;

use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Support\CourseSectionResolver;
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

    private function processRow($row, int $rowNumber): void
    {
        $rowData = $row instanceof Collection ? $row->toArray() : (array) $row;

        if (empty(array_filter($rowData, fn ($v) => trim((string) $v) !== ''))) {
            return;
        }

        $studentIdentifier = trim((string) ($rowData['student_id'] ?? ''));
        $academicSection = trim((string) ($rowData['academic_section'] ?? ''));
        $courseCode = trim((string) ($rowData['course_code'] ?? ''));
        $semesterCode = trim((string) ($rowData['semester_code'] ?? ''));
        $sectionName = trim((string) ($rowData['section_name'] ?? ''));
        $sectionCode = trim((string) ($rowData['section_code'] ?? ''));
        $sectionId = trim((string) ($rowData['section_id'] ?? ''));
        $enrolledAt = trim((string) ($rowData['enrolled_at'] ?? '')) ?: null;
        $studentCodeValue = trim((string) ($rowData['student_code_value'] ?? $rowData['student_code'] ?? '')) ?: null;

        $section = CourseSectionResolver::resolve(
            $courseCode,
            $semesterCode,
            $sectionName,
            $sectionCode,
            $sectionId,
        );

        if (! $section) {
            $this->errors[] = "Row {$rowNumber}: Course section not found. Use course_code + semester_code + section_name (e.g. CS101, F2024, A), or section_code (e.g. CS101_F2024_A). Import course sections before enrollments.";

            return;
        }

        if ($studentIdentifier !== '') {
            $student = $this->findStudent($studentIdentifier, $rowNumber);
            if (! $student) {
                return;
            }

            $this->createOrUpdateEnrollment(
                $student->id,
                $section->id,
                $studentCodeValue ?? $student->student_id,
                $enrolledAt,
            );

            $this->rowCount++;

            return;
        }

        if ($academicSection === '') {
            $this->errors[] = "Row {$rowNumber}: Provide student_id for one student, or academic_section to enroll an entire cohort.";

            return;
        }

        $students = Student::where('academic_section', $academicSection)
            ->orWhereRaw('LOWER(academic_section) = ?', [strtolower($academicSection)])
            ->get();

        if ($students->isEmpty()) {
            $this->errors[] = "Row {$rowNumber}: No students found in academic_section '{$academicSection}'.";

            return;
        }

        foreach ($students as $student) {
            $this->createOrUpdateEnrollment(
                $student->id,
                $section->id,
                $studentCodeValue ?? $student->student_id,
                $enrolledAt,
            );
            $this->rowCount++;
        }
    }

    private function findStudent(string $identifier, int $rowNumber): ?Student
    {
        $student = Student::where('student_id', $identifier)
            ->orWhereRaw('LOWER(student_id) = ?', [strtolower($identifier)])
            ->first();

        if (! $student && preg_match('/^\d+$/', $identifier)) {
            $student = Student::find((int) $identifier);
        }

        if (! $student && filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $student = Student::where('email', $identifier)->first();
        }

        if (! $student) {
            $this->errors[] = "Row {$rowNumber}: Student '{$identifier}' not found.";
        }

        return $student;
    }

    private function createOrUpdateEnrollment(int $studentId, int $sectionId, ?string $studentCode = null, ?string $enrolledAt = null): void
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

            return;
        }

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

    public function rules(): array
    {
        return [
            '*.student_id' => 'nullable|string',
            '*.academic_section' => 'nullable|string',
            '*.course_code' => 'nullable|string',
            '*.semester_code' => 'nullable|string',
            '*.section_name' => 'nullable|string',
            '*.section_code' => 'nullable|string',
            '*.section_id' => 'nullable',
            '*.enrolled_at' => 'nullable|date',
            '*.student_code_value' => 'nullable|string',
        ];
    }

    public function prepareForValidation($data, $index)
    {
        $hasStudent = ! empty(trim((string) ($data['student_id'] ?? '')));
        $hasCohort = ! empty(trim((string) ($data['academic_section'] ?? '')));
        $hasComposite = ! empty(trim((string) ($data['course_code'] ?? '')))
            && ! empty(trim((string) ($data['semester_code'] ?? '')))
            && ! empty(trim((string) ($data['section_name'] ?? '')));
        $hasSectionCode = ! empty(trim((string) ($data['section_code'] ?? '')));
        $hasSectionId = ! empty(trim((string) ($data['section_id'] ?? '')));

        if (! $hasStudent && ! $hasCohort) {
            return null;
        }

        if (! $hasComposite && ! $hasSectionCode && ! $hasSectionId) {
            return null;
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

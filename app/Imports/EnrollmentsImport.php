<?php

namespace App\Imports;

use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Validators\Failure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnrollmentsImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;
    
    protected $rowCount = 0;
    protected $errors = [];
    protected $createdSections = [];
    protected $createdCourseOfferings = [];
    protected $semesterCache = null;

    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            $this->semesterCache = Semester::where('is_active', true)->first() 
                ?? Semester::first();

            if (!$this->semesterCache) {
                throw new \Exception('No semester found in the system');
            }

            foreach ($rows as $index => $row) {
                $this->processRow($row, $index + 2);
            }

            DB::commit();

            $this->addCreationSummary();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Enrollment import failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function processRow($row, $rowNumber)
    {
        $rowData = $row->toArray();

        if (empty(array_filter($rowData))) {
            return;
        }

        $studentIdentifier = trim((string)($rowData['student_id'] ?? ''));
        $sectionIdentifier = trim((string)($rowData['section_id'] ?? ''));
        $enrolledAt = trim((string)($rowData['enrolled_at'] ?? '')) ?: null;
        $studentCodeValue = trim((string)($rowData['student_code_value'] ?? $rowData['student_code'] ?? '')) ?: null;

        if (!$studentIdentifier || !$sectionIdentifier) {
            $this->errors[] = "Row {$rowNumber}: Both student_id and section_id are required";
            return;
        }

        $student = $this->findStudent($studentIdentifier, $rowNumber);
        if (!$student) {
            return;
        }

        $section = $this->findOrCreateSection($sectionIdentifier, $rowNumber);
        if (!$section) {
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

        if (!$student && preg_match('/^\d+$/', $identifier)) {
            $padded = str_pad($identifier, 4, '0', STR_PAD_LEFT);
            $student = Student::where('student_id', 'STU' . $padded)
                ->orWhere('student_id', 'STU' . ltrim($identifier, '0'))
                ->first();

            if (!$student) {
                $student = Student::find((int)$identifier);
            }
        }

        if (!$student && stripos($identifier, 'STU') !== 0 && !preg_match('/^\d+$/', $identifier)) {
            $student = Student::where('student_id', 'STU' . strtoupper($identifier))->first();
        }

        if (!$student && filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $student = Student::where('email', $identifier)->first();
        }

        if (!$student) {
            $this->errors[] = "Row {$rowNumber}: Student '{$identifier}' not found";
        }

        return $student;
    }

    private function findOrCreateSection($sectionIdentifier, $rowNumber)
    {
        $section = $this->findExistingSection($sectionIdentifier);
        
        if ($section) {
            return $section;
        }

        return $this->createNewSection($sectionIdentifier, $rowNumber);
    }

    private function findExistingSection($identifier)
    {
        if (is_numeric($identifier)) {
            $section = Section::find((int)$identifier);
            if ($section) {
                return $section;
            }
        }

        return Section::where('section_name', $identifier)
            ->orWhereRaw('LOWER(section_name) = ?', [strtolower($identifier)])
            ->first();
    }

    private function createNewSection($sectionIdentifier, $rowNumber)
    {
        try {
            $courseOffering = $this->findOrCreateCourseOffering($sectionIdentifier, $rowNumber);
            
            if (!$courseOffering) {
                return null;
            }

            $section = Section::create([
                'course_offering_id' => $courseOffering->id,
                'section_name' => $sectionIdentifier,
                'capacity' => 50,
            ]);

            $this->createdSections[] = $sectionIdentifier;
            return $section;

        } catch (\Exception $e) {
            $this->errors[] = "Row {$rowNumber}: Failed to create section '{$sectionIdentifier}': " . $e->getMessage();
            return null;
        }
    }

    private function findOrCreateCourseOffering($sectionIdentifier, $rowNumber)
    {
        $course = $this->findCourseFromIdentifier($sectionIdentifier);
        
        if (!$course) {
            $this->errors[] = "Row {$rowNumber}: Could not determine course for section '{$sectionIdentifier}'";
            return null;
        }

        $courseOffering = CourseOffering::where('course_id', $course->id)
            ->where('semester_id', $this->semesterCache->id)
            ->first();

        if (!$courseOffering) {
            try {
                $courseOffering = CourseOffering::create([
                    'course_id' => $course->id,
                    'semester_id' => $this->semesterCache->id,
                    'expected_students' => 50,
                ]);

                $this->createdCourseOfferings[] = $course->course_code;

            } catch (\Exception $e) {
                $this->errors[] = "Row {$rowNumber}: Failed to create course offering for '{$course->course_code}': " . $e->getMessage();
                return null;
            }
        }

        return $courseOffering;
    }

    private function findCourseFromIdentifier($sectionIdentifier)
    {
        // First try to extract course code from the identifier
        $courseCode = $this->extractCourseCode($sectionIdentifier);
        
        if ($courseCode) {
            $course = Course::where('course_code', $courseCode)->first();
            if ($course) {
                return $course;
            }
        }

        // If numeric section ID, try to find an existing section and get its course
        if (is_numeric($sectionIdentifier)) {
            $existingSection = Section::find((int)$sectionIdentifier);
            if ($existingSection && $existingSection->course_offering) {
                return $existingSection->course_offering->course;
            }
        }

        // Try direct course lookup by section identifier as course code
        $course = Course::where('course_code', $sectionIdentifier)->first();
        if ($course) {
            return $course;
        }

        // Try by course name
        $course = Course::where('course_name', $sectionIdentifier)->first();
        if ($course) {
            return $course;
        }

        // For numeric identifiers, try to find a course with matching number pattern
        if (is_numeric($sectionIdentifier)) {
            $course = Course::where('course_code', 'like', '%' . $sectionIdentifier . '%')->first();
            if ($course) {
                return $course;
            }
        }

        // As a fallback, try to find any course (for enrollment import to work)
        $fallbackCourse = Course::first();
        if ($fallbackCourse) {
            $this->errors[] = "Warning: Using fallback course '{$fallbackCourse->course_code}' for section '{$sectionIdentifier}'";
            return $fallbackCourse;
        }

        return null;
    }

    private function extractCourseCode($identifier)
    {
        // Try to extract course code from section name patterns
        // Examples: "BSCS-1A" -> "BSCS", "MATH-101" -> "MATH", "CS101" -> "CS"
        
        if (preg_match('/^([A-Z]{2,4})/i', $identifier, $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/([A-Z]{2,4})-\d+[A-Z]?/i', $identifier, $matches)) {
            return strtoupper($matches[1]);
        }

        if (preg_match('/([A-Z]{2,4})\d+/i', $identifier, $matches)) {
            return strtoupper($matches[1]);
        }

        // For numeric identifiers, try to map to known course patterns
        if (is_numeric($identifier)) {
            $num = (int)$identifier;
            
            // Map common numeric ranges to course prefixes
            if ($num >= 100 && $num < 200) return 'BA';  // Business Administration
            if ($num >= 200 && $num < 300) return 'CS';  // Computer Science  
            if ($num >= 300 && $num < 400) return 'MATH'; // Mathematics
            if ($num >= 400 && $num < 500) return 'ENG'; // English
            if ($num >= 500 && $num < 600) return 'SCI'; // Science
        }

        return null;
    }

    private function createOrUpdateEnrollment($studentId, $sectionId, $studentCode = null, $enrolledAt = null)
    {
        $existing = Enrollment::where('student_id', $studentId)
            ->where('section_id', $sectionId)
            ->first();

        $hasStudentCode = \Schema::hasColumn('enrollments', 'student_code');

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

    private function addCreationSummary()
    {
        $summaries = [];

        if (!empty($this->createdCourseOfferings)) {
            $summaries[] = "Created " . count($this->createdCourseOfferings) . " course offerings: " . implode(', ', array_unique($this->createdCourseOfferings));
        }

        if (!empty($this->createdSections)) {
            $summaries[] = "Created " . count($this->createdSections) . " new sections: " . implode(', ', $this->createdSections);
        }

        if (!empty($summaries)) {
            $this->errors[] = implode('. ', $summaries);
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
            $data['student_id'] = trim((string)$data['student_id']);
        }

        if (isset($data['section_id'])) {
            $data['section_id'] = trim((string)$data['section_id']);
        }

        if (isset($data['enrolled_at'])) {
            $data['enrolled_at'] = trim((string)$data['enrolled_at']);
        }

        if (isset($data['student_code_value'])) {
            $data['student_code_value'] = trim((string)$data['student_code_value']);
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

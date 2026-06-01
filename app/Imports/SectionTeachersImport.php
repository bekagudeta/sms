<?php

namespace App\Imports;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Section;
use App\Models\SectionTeacher;
use App\Models\Semester;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SectionTeachersImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected $processedCount = 0;

    protected $createdCount = 0;

    protected $errors = [];

    public function collection(Collection $rows)
    {
        $clearedSections = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $courseCode = trim((string) ($row['course_code'] ?? ''));
            $semesterCode = trim((string) ($row['semester_code'] ?? ''));
            $sectionName = trim((string) ($row['section_name'] ?? ''));
            $legacySectionKey = trim((string) ($row['section_id'] ?? $row['section_code'] ?? ''));

            $teacherIdentifiers = trim((string) (
                $row['teacher_ids']
                ?? $row['teacher_code']
                ?? $row['teacher_id']
                ?? ''
            ));

            $appendFlag = trim((string) ($row['append'] ?? ''));
            $isAppend = in_array(strtolower($appendFlag), ['1', 'true', 'yes'], true);

            if (
                $courseCode === ''
                && $semesterCode === ''
                && $sectionName === ''
                && $legacySectionKey === ''
                && $teacherIdentifiers === ''
            ) {
                continue;
            }

            if ($teacherIdentifiers === '') {
                $this->errors[] = "Row {$rowNumber}: teacher_code (or teacher_id) is required";

                continue;
            }

            $section = $this->resolveSection($courseCode, $semesterCode, $sectionName, $legacySectionKey);

            if (! $section) {
                if ($courseCode !== '' || $semesterCode !== '' || $sectionName !== '') {
                    $this->errors[] = "Row {$rowNumber}: Section not found for course_code '{$courseCode}', semester_code '{$semesterCode}', section_name '{$sectionName}'";
                } else {
                    $this->errors[] = "Row {$rowNumber}: Section '{$legacySectionKey}' not found";
                }

                continue;
            }

            if (! $isAppend && ! in_array($section->id, $clearedSections, true)) {
                SectionTeacher::where('section_id', $section->id)->delete();
                $clearedSections[] = $section->id;
            }

            $teacherCodes = array_filter(array_map('trim', explode(',', $teacherIdentifiers)));

            if (empty($teacherCodes)) {
                $this->errors[] = "Row {$rowNumber}: No valid teacher identifier found";

                continue;
            }

            foreach ($teacherCodes as $rawTeacherCode) {
                if ($rawTeacherCode === '') {
                    continue;
                }

                $teacher = $this->findTeacher($rawTeacherCode);

                if (! $teacher) {
                    $this->errors[] = "Row {$rowNumber}: Teacher '{$rawTeacherCode}' not found";

                    continue;
                }

                try {
                    $existingAssignment = SectionTeacher::where('section_id', $section->id)
                        ->where('teacher_id', $teacher->id)
                        ->first();

                    if (! $existingAssignment) {
                        SectionTeacher::create([
                            'section_id' => $section->id,
                            'teacher_id' => $teacher->id,
                        ]);
                        $this->createdCount++;
                    }

                    $this->processedCount++;
                } catch (\Exception $e) {
                    $sectionLabel = $courseCode !== ''
                        ? "{$courseCode} / {$semesterCode} / {$sectionName}"
                        : $legacySectionKey;

                    $this->errors[] = "Row {$rowNumber}: Error assigning teacher '{$rawTeacherCode}' to section '{$sectionLabel}': ".$e->getMessage();
                }
            }
        }
    }

    /**
     * Resolve a section using course_code + semester_code + section_name (preferred),
     * or fall back to legacy section_id / section_code lookup.
     */
    private function resolveSection(
        string $courseCode,
        string $semesterCode,
        string $sectionName,
        string $legacySectionKey
    ): ?Section {
        if ($courseCode !== '' && $semesterCode !== '' && $sectionName !== '') {
            return $this->findSectionByOffering($courseCode, $semesterCode, $sectionName);
        }

        if ($legacySectionKey !== '') {
            return $this->findSectionLegacy($legacySectionKey);
        }

        return null;
    }

    private function findSectionByOffering(string $courseCode, string $semesterCode, string $sectionName): ?Section
    {
        $course = Course::where('course_code', $courseCode)
            ->orWhereRaw('LOWER(course_code) = ?', [strtolower($courseCode)])
            ->first();

        if (! $course) {
            return null;
        }

        $semester = Semester::where('code', $semesterCode)
            ->orWhereRaw('LOWER(code) = ?', [strtolower($semesterCode)])
            ->first();

        if (! $semester) {
            return null;
        }

        $courseOffering = CourseOffering::where('course_id', $course->id)
            ->where('semester_id', $semester->id)
            ->first();

        if (! $courseOffering) {
            return null;
        }

        $candidates = array_unique(array_filter([
            $sectionName,
            $this->withSectionPrefix($sectionName),
            $this->withoutSectionPrefix($sectionName),
        ]));

        return Section::where('course_offering_id', $courseOffering->id)
            ->where(function ($query) use ($candidates) {
                foreach ($candidates as $candidate) {
                    $query->orWhere('section_name', $candidate)
                        ->orWhereRaw('LOWER(section_name) = ?', [strtolower($candidate)]);
                }
            })
            ->first();
    }

    private function findSectionLegacy(string $sectionIdentifier): ?Section
    {
        if (is_numeric($sectionIdentifier)) {
            $section = Section::find((int) $sectionIdentifier);
            if ($section) {
                return $section;
            }
        }

        return Section::where('section_name', $sectionIdentifier)
            ->orWhereRaw('LOWER(section_name) = ?', [strtolower($sectionIdentifier)])
            ->first();
    }

    private function findTeacher(string $teacherIdentifier): ?Teacher
    {
        $teacherIdentifier = trim($teacherIdentifier);

        $teacher = Teacher::where('teacher_id', $teacherIdentifier)
            ->orWhereRaw('LOWER(teacher_id) = ?', [strtolower($teacherIdentifier)])
            ->orWhere('email', $teacherIdentifier)
            ->orWhereRaw('LOWER(email) = ?', [strtolower($teacherIdentifier)])
            ->first();

        if (! $teacher && is_numeric($teacherIdentifier)) {
            $teacher = Teacher::find((int) $teacherIdentifier);
        }

        return $teacher;
    }

    private function withSectionPrefix(string $sectionName): string
    {
        if (preg_match('/^section\s+/i', $sectionName)) {
            return $sectionName;
        }

        return 'Section '.$sectionName;
    }

    private function withoutSectionPrefix(string $sectionName): string
    {
        return preg_replace('/^section\s+/i', '', $sectionName) ?? $sectionName;
    }

    public function rules(): array
    {
        return [
            '*.course_code' => 'nullable|string',
            '*.semester_code' => 'nullable|string',
            '*.section_name' => 'nullable|string',
            '*.teacher_code' => 'nullable|string',
            '*.teacher_id' => 'nullable|string',
            '*.teacher_ids' => 'nullable|string',
            '*.section_id' => 'nullable',
            '*.section_code' => 'nullable|string',
            '*.append' => 'nullable|string',
        ];
    }

    public function prepareForValidation($data, $index)
    {
        $hasComposite = ! empty(trim((string) ($data['course_code'] ?? '')))
            && ! empty(trim((string) ($data['semester_code'] ?? '')))
            && ! empty(trim((string) ($data['section_name'] ?? '')));

        $hasLegacySection = ! empty(trim((string) ($data['section_id'] ?? $data['section_code'] ?? '')));

        $hasTeacher = ! empty(trim((string) (
            $data['teacher_code']
            ?? $data['teacher_id']
            ?? $data['teacher_ids']
            ?? ''
        )));

        if (! $hasComposite && ! $hasLegacySection && ! $hasTeacher) {
            return null;
        }

        return $data;
    }

    public function getRowCount(): int
    {
        return $this->processedCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

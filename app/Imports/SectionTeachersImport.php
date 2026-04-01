<?php

namespace App\Imports;

use App\Models\Section;
use App\Models\Teacher;
use App\Models\SectionTeacher;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Collection;

class SectionTeachersImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected $processedCount = 0;
    protected $createdCount = 0;
    protected $errors = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            // Skip empty rows
            $sectionIdentifier = trim((string)($row['section_id'] ?? ''));
            $teacherIdentifiers = trim((string)($row['teacher_ids'] ?? ''));

            if ($sectionIdentifier === '' || $teacherIdentifiers === '') {
                continue;
            }

            // Find section by ID or name
            $section = $this->findSection($sectionIdentifier);

            if (!$section) {
                $this->errors[] = "Row " . ($index + 2) . ": Section '{$sectionIdentifier}' not found";
                continue;
            }

            // Parse teacher IDs (comma-separated)
            $teacherIds = array_filter(array_map('trim', explode(',', $teacherIdentifiers)));

            if (empty($teacherIds)) {
                $this->errors[] = "Row " . ($index + 2) . ": No teacher_ids provided";
                continue;
            }

            foreach ($teacherIds as $rawTeacherId) {
                $teacher = $this->findTeacher($rawTeacherId);

                if (!$teacher) {
                    $this->errors[] = "Row " . ($index + 2) . ": Teacher '{$rawTeacherId}' not found";
                    continue;
                }

                try {
                    $existingAssignment = SectionTeacher::where('section_id', $section->id)
                        ->where('teacher_id', $teacher->id)
                        ->first();

                    if (!$existingAssignment) {
                        SectionTeacher::create([
                            'section_id' => $section->id,
                            'teacher_id' => $teacher->id,
                        ]);
                        $this->createdCount++;
                    }

                    // Count every row assignment attempt so imports reflect supplied rows
                    $this->processedCount++;

                } catch (\Exception $e) {
                    $this->errors[] = "Row " . ($index + 2) . ": Error assigning teacher '{$rawTeacherId}' to section '{$sectionIdentifier}': " . $e->getMessage();
                }
            }
        }
    }

    private function findSection(string $sectionIdentifier)
    {
        if (is_numeric($sectionIdentifier)) {
            $section = Section::find((int)$sectionIdentifier);
            if ($section) {
                return $section;
            }
        }

        $section = Section::where('section_name', $sectionIdentifier)
            ->orWhereRaw('LOWER(section_name) = ?', [strtolower($sectionIdentifier)])
            ->orWhere('section_name', 'like', "%{$sectionIdentifier}%")
            ->first();

        return $section;
    }

    private function findTeacher(string $teacherIdentifier)
    {
        $teacherIdentifier = trim($teacherIdentifier);

        $teacher = Teacher::where('teacher_id', $teacherIdentifier)
            ->orWhereRaw('LOWER(teacher_id) = ?', [strtolower($teacherIdentifier)])
            ->orWhere('email', $teacherIdentifier)
            ->orWhereRaw('LOWER(email) = ?', [strtolower($teacherIdentifier)])
            ->first();

        if (!$teacher && is_numeric($teacherIdentifier)) {
            $teacher = Teacher::find((int)$teacherIdentifier);
        }

        return $teacher;
    }

    public function rules(): array
    {
        return [
            '*.section_id' => 'required',
            '*.teacher_ids' => 'required|string',
            '*.append' => 'nullable|string'
        ];
    }

    public function prepareForValidation($data, $index)
    {
        // Skip completely empty rows by returning null (will be filtered out)
        if (empty(trim((string)($data['section_id'] ?? ''))) && empty(trim((string)($data['teacher_ids'] ?? '')))) {
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

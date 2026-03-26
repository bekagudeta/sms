<?php

namespace App\Imports;

use App\Models\Section;
use App\Models\Teacher;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Collection;

class SectionTeachersImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected $rowCount = 0;
    protected $errors = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Skip empty rows
            if (empty($row['section_id']) || empty($row['teacher_ids'])) {
                continue;
            }

            // Find section
            $section = Section::find($row['section_id']);
            if (!$section) {
                $this->errors[] = "Section ID {$row['section_id']} not found";
                continue;
            }

            // Parse teacher IDs (comma-separated)
            $teacherIds = explode(',', $row['teacher_ids']);
            $validTeacherIds = [];

            foreach ($teacherIds as $teacherId) {
                $teacherId = trim($teacherId);
                
                // Try finding by teacher_id (custom ID) or database id
                $teacher = Teacher::where('teacher_id', $teacherId)->first();
                
                if (!$teacher && is_numeric($teacherId)) {
                    $teacher = Teacher::find($teacherId);
                }

                if ($teacher) {
                    $validTeacherIds[] = $teacher->id;
                } else {
                    $this->errors[] = "Teacher '{$teacherId}' not found for section {$row['section_id']}";
                }
            }

            // Attach teachers to section (sync = replace existing)
            if (!empty($validTeacherIds)) {
                if (!empty($row['append']) && strtolower($row['append']) === 'yes') {
                    // Append mode - don't remove existing teachers
                    $section->teachers()->syncWithoutDetaching($validTeacherIds);
                } else {
                    // Replace mode - sync replaces all existing
                    $section->teachers()->sync($validTeacherIds);
                }
                $this->rowCount++;
            }
        }
    }

    public function rules(): array
    {
        return [
            '*.section_id' => 'required|integer',
            '*.teacher_ids' => 'required|string',
            '*.append' => 'nullable|string'
        ];
    }

    public function prepareForValidation($data, $index)
    {
        // Skip completely empty rows by returning null (will be filtered out)
        if (empty($data['section_id']) && empty($data['teacher_id'])) {
            return null;
        }
        return $data;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

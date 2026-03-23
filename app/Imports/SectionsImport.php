<?php

namespace App\Imports;

use App\Models\Section;
use App\Models\CourseOffering;
use App\Models\Course;
use App\Models\Semester;
use App\Models\Teacher;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Collection;

class SectionsImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected $rowCount = 0;

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {

            // Skip empty rows
            if (empty($row['course_code']) || (empty($row['semester_id']) && empty($row['semester_code'])) || empty($row['section_name'])) {
                continue;
            }

            // Find course offering by course code + semester
            $course = Course::where('course_code', $row['course_code'])->first();
            if (!$course) {
                continue;
            }

            $semester = null;
            if (!empty($row['semester_id'])) {
                $semester = Semester::find($row['semester_id']);
            }
            if (!$semester && !empty($row['semester_code'])) {
                $semester = Semester::where('code', $row['semester_code'])->first();
            }
            if (!$semester) {
                continue;
            }

            $courseOffering = CourseOffering::where('course_id', $course->id)
                ->where('semester_id', $semester->id)
                ->first();
            if (!$courseOffering) {
                continue;
            }

            $section = Section::updateOrCreate(
                [
                    'course_offering_id' => $courseOffering->id,
                    'section_name' => $row['section_name']
                ],
                [
                    'capacity' => $row['capacity'] ?? 30
                ]
            );

            // Handle teachers
            if (!empty($row['teacher_ids'])) {
                $teacherIds = explode(',', $row['teacher_ids']);
                $validTeacherIds = [];

                foreach ($teacherIds as $teacherId) {
                    $teacher = Teacher::where('teacher_id', trim($teacherId))->first();
                    if ($teacher) {
                        $validTeacherIds[] = $teacher->id;
                    }
                }

                if (!empty($validTeacherIds)) {
                    $section->teachers()->sync($validTeacherIds);
                }
            }

            $this->rowCount++;
        }
    }

    public function rules(): array
    {
        return [
            '*.course_code' => 'required|string',
            '*.semester_id' => 'nullable|integer',
            '*.semester_code' => 'nullable|string',
            '*.section_name' => 'required|string',
            '*.capacity' => 'nullable|integer|min:1',
            '*.teacher_ids' => 'nullable|string'
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
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
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;   // ← ADD
use Illuminate\Support\Collection;

class SectionsImport implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows  // ← ADD
{
    protected $rowCount = 0;

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {

            if (empty($row['section_name'])) {
                continue;
            }

            $courseOffering = null;

            // Option 1: Direct course_offering_id provided
            if (!empty($row['course_offering_id'])) {
                $courseOffering = CourseOffering::find($row['course_offering_id']);
            }

            // Option 2: Find by course_code + semester_code (Excel format in the screenshot)
            if (!$courseOffering && !empty($row['course_code'])) {
                $course = Course::where('course_code', trim($row['course_code']))->first();
                if ($course) {
                    $semester = null;

                    if (!empty($row['semester_id'])) {
                        $semester = Semester::find($row['semester_id']);
                    }
                    if (!$semester && !empty($row['semester_code'])) {
                        $semester = Semester::where('code', trim($row['semester_code']))->first();
                    }
                    if ($semester) {
                        $courseOffering = CourseOffering::where('course_id', $course->id)
                            ->where('semester_id', $semester->id)
                            ->first();
                    }
                }
            }

            // Can't resolve a course offering — skip this row
            if (!$courseOffering) {
                continue;
            }

            $section = Section::updateOrCreate(
                [
                    'course_offering_id' => $courseOffering->id,
                    'section_name'       => $row['section_name'],
                ],
                [
                    'capacity' => $row['capacity'] ?? 30,
                ]
            );

            // Handle teachers
            if (!empty($row['teacher_ids'])) {
                $teacherIds      = explode(',', $row['teacher_ids']);
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

    public function prepareForValidation(array $data, int $index): array
    {
        // Try to resolve course_offering_id from course_code + semester_code
        // so the nullable rule below doesn't block legitimate rows
        if (empty($data['course_offering_id']) && !empty($data['course_code'])) {
            $course = Course::where('course_code', trim($data['course_code']))->first();
            if ($course) {
                $semester = null;

                if (!empty($data['semester_id'])) {
                    $semester = Semester::find($data['semester_id']);
                }
                if (!$semester && !empty($data['semester_code'])) {
                    $semester = Semester::where('code', trim($data['semester_code']))->first();
                }
                if ($semester) {
                    $courseOffering = CourseOffering::where('course_id', $course->id)
                        ->where('semester_id', $semester->id)
                        ->first();
                    if ($courseOffering) {
                        $data['course_offering_id'] = $courseOffering->id;
                    }
                }
            }
        }

        return $data;
    }

    public function rules(): array
    {
        return [
            // course_offering_id is resolved in prepareForValidation;
            // the Excel sheet only has course_code + semester_code so we
            // cannot require this field at validation time — collection()
            // skips rows where it still can't be resolved.
            '*.course_offering_id' => 'nullable|integer',

            '*.section_name'       => 'required|string',
            '*.capacity'           => 'nullable|integer|min:1',
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
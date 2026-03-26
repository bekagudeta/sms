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

            // Skip empty rows - check if we have required section name and some way to identify course offering
            if (empty($row['section_name'])) {
                continue;
            }

            $courseOffering = null;

            // Option 1: Direct course_offering_id provided (your Excel format)
            if (!empty($row['course_offering_id'])) {
                $courseOffering = CourseOffering::find($row['course_offering_id']);
            }

            // Option 2: Find course offering by course code + semester (old format)
            if (!$courseOffering && !empty($row['course_code'])) {
                $course = Course::where('course_code', $row['course_code'])->first();
                if ($course) {
                    $semester = null;
                    if (!empty($row['semester_id'])) {
                        $semester = Semester::find($row['semester_id']);
                    }
                    if (!$semester && !empty($row['semester_code'])) {
                        $semester = Semester::where('code', $row['semester_code'])->first();
                    }
                    if ($semester) {
                        $courseOffering = CourseOffering::where('course_id', $course->id)
                            ->where('semester_id', $semester->id)
                            ->first();
                    }
                }
            }

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

    public function prepareForValidation($data, $index)
    {
        if (empty($data['course_offering_id']) && !empty($data['course_code'])) {
            $course = Course::where('course_code', $data['course_code'])->first();
            if ($course) {
                $semester = null;
                if (!empty($data['semester_id'])) {
                    $semester = Semester::find($data['semester_id']);
                }
                if (!$semester && !empty($data['semester_code'])) {
                    $semester = Semester::where('code', $data['semester_code'])->first();
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
            '*.course_offering_id' => 'required|integer',
            '*.section_name' => 'required|string',
            '*.capacity' => 'nullable|integer|min:1'
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
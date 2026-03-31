<?php

namespace App\Imports;

use App\Models\CourseOffering;
use App\Models\Course;
use App\Models\Semester;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

class CourseOfferingsImport implements ToCollection, WithHeadingRow
{
    protected $rowCount = 0;
    protected $errors = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            if (
                empty($row['course_name'])
                && empty($row['course_code'])
                && empty($row['course_id'])
                && empty($row['semester_id'])
                && empty($row['semester_code'])
                && empty($row['semester_name'])
            ) {
                continue;
            }

            $rowNumber = $index + 2;

            $course = null;
            if (!empty($row['course_id'])) {
                $course = Course::find($row['course_id']);
            }
            if (!$course && !empty($row['course_code'])) {
                $course = Course::where('course_code', trim($row['course_code']))->first();
            }
            if (!$course && !empty($row['course_name'])) {
                $course = Course::where('course_name', trim($row['course_name']))->first();
            }

            if (!$course) {
                $identifier = $row['course_code'] ?? $row['course_name'] ?? $row['course_id'] ?? 'unknown';
                $this->errors[] = "Row {$rowNumber}: Course '{$identifier}' was not found. Import courses first.";
                continue;
            }

            $semester = null;
            if (!empty($row['semester_id'])) {
                $semester = Semester::find($row['semester_id']);
            }
            if (!$semester && !empty($row['semester_code'])) {
                $semester = Semester::where('code', trim($row['semester_code']))->first();
            }
            if (!$semester && !empty($row['semester_name'])) {
                $semester = Semester::where('name', trim($row['semester_name']))->first();
            }

            if (!$semester) {
                $identifier = $row['semester_code'] ?? $row['semester_name'] ?? $row['semester_id'] ?? 'unknown';
                $this->errors[] = "Row {$rowNumber}: Semester '{$identifier}' was not found. Import semesters first.";
                continue;
            }

            CourseOffering::updateOrCreate(
                [
                    'course_id' => $course->id,
                    'semester_id' => $semester->id,
                ],
                [
                    'expected_students' => max(1, (int) ($row['expected_students'] ?? 30)),
                ]
            );

            $this->rowCount++;
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
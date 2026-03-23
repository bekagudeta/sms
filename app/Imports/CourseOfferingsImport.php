<?php

namespace App\Imports;

use App\Models\CourseOffering;
use App\Models\Course;
use App\Models\Semester;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Collection;

class CourseOfferingsImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected $rowCount = 0;

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {

            // Skip empty rows
            if (empty($row['course_code']) || (empty($row['semester_id']) && empty($row['semester_code']))) {
                continue;
            }

            // Find course by code
            $course = Course::where('course_code', $row['course_code'])->first();
            if (!$course) {
                continue; // Skip if course doesn't exist
            }

            // Find semester by ID or code
            $semester = null;
            if (!empty($row['semester_id'])) {
                $semester = Semester::find($row['semester_id']);
            }
            if (!$semester && !empty($row['semester_code'])) {
                $semester = Semester::where('code', $row['semester_code'])->first();
            }
            if (!$semester) {
                continue; // Skip if semester doesn't exist
            }

            CourseOffering::updateOrCreate(
                [
                    'course_id' => $course->id,
                    'semester_id' => $semester->id
                ],
                [
                    'expected_students' => $row['expected_students'] ?? 30
                ]
            );

            $this->rowCount++;
        }
    }

    public function rules(): array
    {
        return [
            '*.course_code' => 'required|string',
            '*.semester_id' => 'nullable|integer',
            '*.semester_code' => 'nullable|string',
            '*.expected_students' => 'nullable|integer|min:1'
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
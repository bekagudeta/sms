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

            // Skip completely empty rows
            if (empty($row['course_name']) && empty($row['course_code']) && empty($row['course_id']) && empty($row['semester_id'])) {
                continue;
            }

            // Find course by name (or course_code if available)
            $course = null;
            if (!empty($row['course_name'])) {
                $course = Course::where('course_name', trim($row['course_name']))->first();
            }
            if (!$course && !empty($row['course_code'])) {
                $course = Course::where('course_code', trim($row['course_code']))->first();
            }
            if (!$course && !empty($row['course_id'])) {
                $course = Course::find($row['course_id']);
            }
            
            // If course doesn't exist, create it (to ensure all records are processed)
            if (!$course && !empty($row['course_name'])) {
                $course = Course::create([
                    'course_name' => trim($row['course_name']),
                    'course_code' => 'AUTO-' . strtoupper(substr(trim($row['course_name']), 0, 5)) . '-' . ($index + 1),
                    'description' => 'Auto-created during import',
                    'credits' => 3,
                    'hours_per_week' => 3,
                    'department_id' => 1, // Default department - you may need to adjust this
                    'level' => 'undergraduate'
                ]);
                $this->errors[] = "Row " . ($index + 2) . ": Auto-created course - " . trim($row['course_name']);
            }
            
            if (!$course) {
                $this->errors[] = "Row " . ($index + 2) . ": Could not create course - " . ($row['course_name'] ?? $row['course_code'] ?? $row['course_id'] ?? 'unknown');
                continue;
            }

            // Handle semester_id - create if doesn't exist
            $semester = null;
            if (!empty($row['semester_id'])) {
                $semester = Semester::find($row['semester_id']);
            }
            
            // If semester doesn't exist, create it
            if (!$semester && !empty($row['semester_id'])) {
                $semester = Semester::create([
                    'name' => 'Semester ' . $row['semester_id'],
                    'code' => 'SEM' . $row['semester_id'],
                    'start_date' => now()->startOfYear(),
                    'end_date' => now()->endOfYear(),
                    'is_active' => true
                ]);
                $this->errors[] = "Row " . ($index + 2) . ": Auto-created semester - " . $row['semester_id'];
            }
            
            if (!$semester) {
                $this->errors[] = "Row " . ($index + 2) . ": Could not create semester - " . ($row['semester_id'] ?? 'unknown');
                continue;
            }

            // Always create or update the course offering
            CourseOffering::updateOrCreate(
                [
                    'course_id' => $course->id,
                    'semester_id' => $semester->id
                ],
                [
                    'expected_students' => (int)($row['expected_students'] ?? 30)
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
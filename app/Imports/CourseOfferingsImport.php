<?php

namespace App\Imports;

use App\Models\Course;
use App\Models\Semester;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CourseOfferingsImport implements ToCollection, WithHeadingRow
{
    protected int $rowCount = 0;
    protected array $errors = [];

    protected $courses;
    protected $semesters;

    public function __construct()
    {
        // 🔥 Load once (NO N+1)
        $this->courses = Course::all()->keyBy('course_code');
        $this->semesters = Semester::all()->keyBy('code');
    }

    public function collection(Collection $rows)
    {
        $batch = [];

        foreach ($rows as $index => $row) {

            $rowNumber = $index + 2;

            if (empty($row['course_code']) || empty($row['semester_code'])) {
                continue;
            }

            $course = $this->courses[trim($row['course_code'])] ?? null;
            $semester = $this->semesters[trim($row['semester_code'])] ?? null;

            if (!$course) {
                $this->errors[] = "Row {$rowNumber}: Course not found ({$row['course_code']})";
                continue;
            }

            if (!$semester) {
                $this->errors[] = "Row {$rowNumber}: Semester not found ({$row['semester_code']})";
                continue;
            }

            $batch[] = [
                'course_id' => $course->id,
                'semester_id' => $semester->id,
                'expected_students' => max(1, (int) ($row['expected_students'] ?? 30)),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $this->rowCount++;

            // 🔥 Chunk insert
            if (count($batch) >= 500) {
                $this->upsertBatch($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            $this->upsertBatch($batch);
        }
    }

    protected function upsertBatch(array $batch)
    {
        DB::table('course_offerings')->upsert(
            $batch,
            ['course_id', 'semester_id'],
            ['expected_students', 'updated_at']
        );
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
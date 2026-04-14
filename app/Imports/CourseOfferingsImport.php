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

    protected $coursesById;
    protected $coursesByCode;
    protected $semestersById;
    protected $semestersByCode;

    public function __construct()
    {
        $courses = Course::all();
        $this->coursesById = $courses->keyBy('id');
        $this->coursesByCode = $courses->mapWithKeys(function ($course) {
            return [$this->normalizeKey($course->course_code) => $course];
        });

        $semesters = Semester::all();
        $this->semestersById = $semesters->keyBy('id');
        $this->semestersByCode = $semesters->mapWithKeys(function ($semester) {
            return [$this->normalizeKey($semester->code) => $semester];
        });
    }

    public function collection(Collection $rows)
    {
        $batch = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $courseId = $this->normalizeId($row['course_id'] ?? null);
            $courseCode = $this->normalizeKey($row['course_code'] ?? null);
            $semesterId = $this->normalizeId($row['semester_id'] ?? null);
            $semesterCode = $this->normalizeKey($row['semester_code'] ?? null);

            $courseById = $courseId ? $this->coursesById[$courseId] ?? null : null;
            $courseByCode = $courseCode ? $this->coursesByCode[$courseCode] ?? null : null;

            if ($courseById && $courseByCode && $courseById->id !== $courseByCode->id) {
                $this->errors[] = "Row {$rowNumber}: course_id {$courseId} does not match course_code {$row['course_code']}";
                continue;
            }

            $course = $courseById ?? $courseByCode;

            $semesterById = $semesterId ? $this->semestersById[$semesterId] ?? null : null;
            $semesterByCode = $semesterCode ? $this->semestersByCode[$semesterCode] ?? null : null;

            if ($semesterById && $semesterByCode && $semesterById->id !== $semesterByCode->id) {
                $this->errors[] = "Row {$rowNumber}: semester_id {$semesterId} does not match semester_code {$row['semester_code']}";
                continue;
            }

            $semester = $semesterById ?? $semesterByCode;

            if (!$course) {
                $identifier = $row['course_code'] ?? $row['course_id'] ?? 'unknown';
                $this->errors[] = "Row {$rowNumber}: Course not found ({$identifier})";
                continue;
            }

            if (!$semester) {
                $identifier = $row['semester_code'] ?? $row['semester_id'] ?? 'unknown';
                $this->errors[] = "Row {$rowNumber}: Semester not found ({$identifier})";
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

    protected function normalizeKey($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;
        $value = str_replace("\xEF\xBB\xBF", '', $value);
        $value = trim($value);

        return $value === '' ? null : strtolower($value);
    }

    protected function normalizeId($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
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
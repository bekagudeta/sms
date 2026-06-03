<?php

namespace App\Imports;

use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Promote existing students to a new year level / cohort without re-importing profiles.
 * For a new term, import enrollments only — student records stay in the database.
 */
class StudentPromotionsImport implements ToCollection, WithChunkReading, WithHeadingRow, WithValidation
{
    public $updatedCount = 0;

    public $skippedCount = 0;

    protected $rowCount = 0;

    public function chunkSize(): int
    {
        return 100;
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $this->rowCount++;

                $studentId = trim((string) ($row['student_id'] ?? ''));
                $student = Student::where('student_id', $studentId)->first();

                if (! $student) {
                    $this->skippedCount++;
                    throw new \Exception(
                        "Promotion failed: student_id '{$studentId}' not found. Import students first or check the ID."
                    );
                }

                $updates = [];

                $level = $this->optionalString($row['level'] ?? null);
                if ($level !== null) {
                    $updates['level'] = $level;
                }

                $academicSection = $this->optionalString($row['academic_section'] ?? $row['section'] ?? null);
                if ($academicSection !== null) {
                    $updates['academic_section'] = $academicSection;
                }

                $status = $this->optionalString($row['status'] ?? null);
                if ($status !== null) {
                    $updates['status'] = $status;
                }

                if (empty($updates)) {
                    $this->skippedCount++;

                    continue;
                }

                $student->update($updates);
                $this->updatedCount++;
            }
        });
    }

    public function rules(): array
    {
        return [
            '*.student_id' => 'required|string',
            '*.level' => 'nullable|string',
            '*.academic_section' => 'nullable|string',
            '*.section' => 'nullable|string',
            '*.status' => 'nullable|string',
        ];
    }

    protected function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}

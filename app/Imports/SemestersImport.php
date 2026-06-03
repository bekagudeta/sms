<?php

namespace App\Imports;

use App\Models\Semester;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;

class SemestersImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected $rowCount = 0;

    /**
     * Converts a value that may be an Excel serial date, a timestamp,
     * or an already-formatted date string into a Y-m-d string.
     * Returns null if the value is empty.
     */
    protected function parseDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Excel stores dates as numeric serial numbers (e.g. 45678)
        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject($value))
                ->format('Y-m-d');
        }

        // Already a date string — normalise it to Y-m-d
        return Carbon::parse($value)->format('Y-m-d');
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $this->rowCount++;

            $startDate = $this->parseDate($row['start_date']);
            $academicYear = trim((string) ($row['academic_year'] ?? ''));
            if ($academicYear === '' && $startDate) {
                $academicYear = substr($startDate, 0, 4);
            }

            Semester::updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'academic_year' => $academicYear ?: null,
                    'start_date' => $startDate,
                    'end_date' => $this->parseDate($row['end_date']),
                    'is_active' => filter_var($row['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ]
            );
        }
    }

    public function rules(): array
    {
        return [
            'name'       => 'required|string',
            'code'       => 'required|string',
            'academic_year' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
        ];
    }

    /**
     * Transform raw row data BEFORE validation runs,
     * so the validator always receives a proper date string.
     */
    public function prepareForValidation(array $data, int $index): array
    {
        $data['start_date'] = $this->parseDate($data['start_date'] ?? null);
        $data['end_date']   = $this->parseDate($data['end_date']   ?? null);

        return $data;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
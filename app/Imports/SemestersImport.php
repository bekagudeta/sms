<?php

namespace App\Imports;

use App\Models\Semester;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;  // ← ADD THIS
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;

class SemestersImport implements ToCollection, WithHeadingRow, WithValidation, SkipsEmptyRows  // ← ADD SkipsEmptyRows
{
    protected $rowCount = 0;

    protected function parseDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject($value))
                ->format('Y-m-d');
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if (empty($row['name']) && empty($row['code'])) {
                continue;
            }

            $this->rowCount++;

            $startDate    = $this->parseDate($row['start_date']);
            $academicYear = trim((string) ($row['academic_year'] ?? ''));
            if ($academicYear === '' && $startDate) {
                $academicYear = substr($startDate, 0, 4);
            }

            Semester::updateOrCreate(
                ['code' => $row['code']],
                [
                    'name'          => $row['name'],
                    'academic_year' => $academicYear ?: null,
                    'start_date'    => $startDate,
                    'end_date'      => $this->parseDate($row['end_date']),
                    'is_active'     => filter_var($row['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ]
            );
        }
    }

    public function rules(): array
    {
        return [
            '*.name'          => 'required|string',
            '*.code'          => 'required|string',
            '*.academic_year' => 'nullable|string',
            '*.start_date'    => 'required|date_format:Y-m-d',
            '*.end_date'      => 'required|date_format:Y-m-d',
        ];
    }

    public function prepareForValidation(array $data, int $index): array
    {
        // Always cast academic_year to string BEFORE validation
        if (isset($data['academic_year']) && $data['academic_year'] !== '') {
            $data['academic_year'] = (string) (int) $data['academic_year'];
        } else {
            $data['academic_year'] = null;
        }

        // Parse dates to Y-m-d so date_format:Y-m-d validation passes
        $data['start_date'] = $this->parseDate($data['start_date'] ?? null);
        $data['end_date']   = $this->parseDate($data['end_date']   ?? null);

        return $data;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
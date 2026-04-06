<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DepartmentsImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected int $rowCount = 0;
    protected array $errors = [];

    public function collection(Collection $rows)
    {
        $batch = [];

        foreach ($rows as $index => $row) {

            if (empty($row['code'])) {
                continue;
            }

            $rowNumber = $index + 2;

            try {
                $code = strtolower(trim($row['code']));
                $name = trim($row['name']);

                if (!$code || !$name) {
                    $this->errors[] = "Row {$rowNumber}: Invalid data";
                    continue;
                }

                $batch[] = [
                    'code' => $code,
                    'name' => $name,
                    'description' => $row['description'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $this->rowCount++;

                if (count($batch) >= 500) {
                    $this->upsertBatch($batch);
                    $batch = [];
                }

            } catch (\Exception $e) {
                $this->errors[] = "Row {$rowNumber}: " . $e->getMessage();
            }
        }

        if (!empty($batch)) {
            $this->upsertBatch($batch);
        }
    }

    protected function upsertBatch(array $batch)
    {
        DB::table('departments')->upsert(
            $batch,
            ['code'],
            ['name', 'description', 'updated_at']
        );
    }

    public function rules(): array
    {
        return [
            '*.code' => 'required|string|max:10',
            '*.name' => 'required|string|max:255',
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
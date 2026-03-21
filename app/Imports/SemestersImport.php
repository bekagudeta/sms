<?php

namespace App\Imports;

use App\Models\Semester;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SemestersImport implements ToModel, WithHeadingRow, WithValidation
{
    protected $rowCount = 0;

    public function model(array $row)
    {
        $this->rowCount++;
        
        return new Semester([
            'name' => $row['name'],
            'code' => $row['code'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'is_active' => $row['is_active'] ?? false
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'code' => 'required|string|unique:semesters,code',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date'
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
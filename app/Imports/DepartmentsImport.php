<?php

namespace App\Imports;

use App\Models\Department;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\Log;

class DepartmentsImport implements ToModel, WithHeadingRow, WithValidation
{
    protected $rowCount = 0;

    public function model(array $row)
    {
        $this->rowCount++;
        
        // Log the row for debugging
        Log::info('Processing department row: ', $row);
        
        try {
            // Use updateOrCreate to handle duplicates
            return Department::updateOrCreate(
                ['code' => $row['code']], // Search criteria
                [
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error creating department from row: ' . $e->getMessage(), $row);
            throw $e;
        }
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:10', // Remove unique constraint for import
            'name' => 'required|string|max:255'
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
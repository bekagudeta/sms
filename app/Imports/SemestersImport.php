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

        // Convert string values to appropriate types
        $isActive = $this->parseBoolean($row['is_active'] ?? '0');
        
        return new Semester([
            'name' => trim($row['name']),
            'code' => strtoupper(trim($row['code'])),
            'start_date' => $this->parseDate($row['start_date']),
            'end_date' => $this->parseDate($row['end_date']),
            'is_active' => $isActive
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:semesters,code',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after:start_date',
            'is_active' => 'nullable|in:0,1,true,false,yes,no'
        ];
    }

    /**
     * Convert string value to boolean
     * Accepts: 1, true, yes (case-insensitive) = true
     * Accepts: 0, false, no (case-insensitive) = false
     */
    protected function parseBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));
        
        return in_array($value, ['1', 'true', 'yes'], true);
    }

    /**
     * Parse date string to ensure proper format
     */
    protected function parseDate($value): string
    {
        // If already in correct format, return as is
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // Try to parse and reformat common date formats
        try {
            $date = \DateTime::createFromFormat('d/m/Y', $value) ?: 
                    \DateTime::createFromFormat('m/d/Y', $value) ?: 
                    \DateTime::createFromFormat('Y-m-d', $value);
            
            if ($date) {
                return $date->format('Y-m-d');
            }
        } catch (\Exception $e) {
            // Fall through to return original value
        }

        return $value;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
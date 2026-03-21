<?php

namespace App\Imports;

use App\Models\Room;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\Log;

class RoomsImport implements ToModel, WithHeadingRow, WithValidation
{
    protected $rowCount = 0;

    public function model(array $row)
    {
        $this->rowCount++;
        
        // Log the row data for debugging
        Log::info('Processing room row: ', $row);
        
        try {
            // Use updateOrCreate to handle duplicates
            return Room::updateOrCreate(
                ['room_code' => $row['room_code']], // Search criteria
                [
                    'building' => $row['building'],
                    'floor' => $row['floor'],
                    'capacity' => $row['capacity'],
                    'type' => $row['type'],
                    'has_projector' => $row['has_projector'] ?? false,
                    'has_computers' => $row['has_computers'] ?? false,
                    'computer_count' => $row['computer_count'] ?? 0
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error creating room from row: ' . $e->getMessage(), $row);
            throw $e;
        }
    }

    public function rules(): array
    {
        return [
            'room_code' => 'required|string',
            'building' => 'required|string',
            'floor' => 'required|integer|min:0',
            'capacity' => 'required|integer|min:1',
            'type' => 'required|string|max:50' // Allow any string type with max length
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
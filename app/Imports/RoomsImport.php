<?php

namespace App\Imports;

use App\Models\Room;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\Log;

class RoomsImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected $rowCount = 0;

    public function collection(Collection $rows)
    {
        // Clear all existing rooms first to ensure proper ordering
        Room::query()->delete();
        
        // Sort the collection by building, then floor, then room_code
        $sortedRows = $rows->sortBy([
            ['building', 'asc'],
            ['floor', 'asc'],
            ['room_code', 'asc']
        ]);

        foreach ($sortedRows as $row) {
            $this->rowCount++;
            
            try {
                // Create new room (no updateOrCreate since we cleared all)
                Room::create([
                    'room_code' => $row['room_code'],
                    'building' => $row['building'],
                    'floor' => $row['floor'],
                    'capacity' => $row['capacity'],
                    'type' => $row['type'],
                    'has_projector' => $row['has_projector'] ?? false,
                    'has_computers' => $row['has_computers'] ?? false,
                    'computer_count' => $row['computer_count'] ?? 0
                ]);
            } catch (\Exception $e) {
                Log::error('Error creating room from row: ' . $e->getMessage(), $row->toArray());
                throw $e;
            }
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
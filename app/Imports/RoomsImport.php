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
        $sortedRows = $rows->sortBy([
            ['building', 'asc'],
            ['floor', 'asc'],
            ['room_code', 'asc']
        ]);

        foreach ($sortedRows as $row) {
            if (empty($row['room_code'])) {
                continue;
            }

            try {
                Room::updateOrCreate(
                    ['room_code' => trim((string) $row['room_code'])],
                    [
                        'building' => trim((string) ($row['building'] ?? '')),
                        'floor' => (int) ($row['floor'] ?? 0),
                        'capacity' => (int) ($row['capacity'] ?? 0),
                        'type' => strtolower(trim((string) ($row['type'] ?? 'lecture'))),
                        'has_projector' => $this->toBoolean($row['has_projector'] ?? false),
                        'has_computers' => $this->toBoolean($row['has_computers'] ?? false),
                        'computer_count' => (int) ($row['computer_count'] ?? 0),
                    ]
                );

                $this->rowCount++;
            } catch (\Exception $e) {
                Log::error('Error creating room from row: ' . $e->getMessage(), is_array($row) ? $row : $row->toArray());
                throw $e;
            }
        }
    }

    protected function toBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true);
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
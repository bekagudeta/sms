<?php

namespace App\Imports;

use App\Models\Room;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RoomsImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected int $rowCount = 0;
    protected array $failedRows = [];

    public function collection(Collection $rows)
    {
        $batch = [];

        foreach ($rows as $index => $row) {

            if (empty($row['room_code'])) {
                continue;
            }

            try {
                $batch[] = [
                    'room_code' => trim((string) $row['room_code']),
                    'building' => trim((string) ($row['building'] ?? '')),
                    'floor' => (int) ($row['floor'] ?? 0),
                    'capacity' => (int) ($row['capacity'] ?? 0),
                    'type' => strtolower(trim((string) ($row['type'] ?? 'lecture'))),
                    'has_projector' => $this->toBoolean($row['has_projector'] ?? false),
                    'has_computers' => $this->toBoolean($row['has_computers'] ?? false),
                    'computer_count' => (int) ($row['computer_count'] ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $this->rowCount++;

                // 🔥 Insert in chunks of 500
                if (count($batch) >= 500) {
                    $this->upsertBatch($batch);
                    $batch = [];
                }

            } catch (\Exception $e) {
                $this->failedRows[] = [
                    'row' => $index + 1,
                    'error' => $e->getMessage(),
                    'data' => $row->toArray()
                ];

                Log::error('Room import failed', $this->failedRows);
            }
        }

        // Insert remaining rows
        if (!empty($batch)) {
            $this->upsertBatch($batch);
        }
    }

    protected function upsertBatch(array $batch)
    {
        DB::table('rooms')->upsert(
            $batch,
            ['room_code'], // unique key
            [
                'building',
                'floor',
                'capacity',
                'type',
                'has_projector',
                'has_computers',
                'computer_count',
                'updated_at'
            ]
        );
    }

    protected function toBoolean($value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true);
    }

    public function rules(): array
    {
        return [
            '*.room_code' => 'required|string|distinct',
            '*.building' => 'required|string|max:100',
            '*.floor' => 'required|integer|min:0|max:100',
            '*.capacity' => 'required|integer|min:1|max:1000',
            '*.type' => 'required|in:lecture,lab,office',
            '*.computer_count' => 'nullable|integer|min:0|max:500',
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function getFailedRows(): array
    {
        return $this->failedRows;
    }
}
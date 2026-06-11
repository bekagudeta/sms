<?php

namespace App\Imports;

use App\Models\Timeslot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class TimeslotsImport implements ToCollection, WithHeadingRow, WithValidation
{
    protected $rowCount = 0;

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $day = ucfirst(strtolower(trim($row['day_of_week'] ?? '')));
            $start = $this->formatTime($row['start_time']);
            $end = $this->formatTime($row['end_time']);
            $slotCode = trim($row['slot_code'] ?? '');
            $studentType = trim($row['student_type'] ?? '') ?: null;

            if (! $day || ! $start || ! $end || ! $slotCode) {
                continue;
            }

            Timeslot::updateOrCreate(
                ['slot_code' => $slotCode],
                [
                    'day_of_week' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                    'slot_code' => $slotCode,
                    'student_type' => $studentType,
                ]
            );

            $this->rowCount++;
        }
    }

    protected function formatTime($time): string
    {
        try {
            // Handle Excel time format (decimal) and string format
            if (is_numeric($time)) {
                // Excel stores time as decimal fraction of 24 hours
                $seconds = $time * 86400;
                $hours = floor($seconds / 3600);
                $minutes = floor(($seconds % 3600) / 60);
                $seconds = $seconds % 60;

                return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
            } else {
                // Handle string time format
                $carbon = Carbon::parse($time);

                return $carbon->format('H:i:s');
            }
        } catch (\Exception $e) {
            return '00:00:00';
        }
    }

    public function rules(): array
    {
        return [
            '*.day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            '*.start_time' => 'required',
            '*.end_time' => 'required',
            '*.slot_code' => 'required|string',
            '*.student_type' => 'nullable|string',
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}

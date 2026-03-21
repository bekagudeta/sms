<?php

namespace App\Imports;

use App\Models\Timeslot;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class TimeslotsImport implements ToModel, WithHeadingRow, WithValidation
{
    protected $rowCount = 0;

    public function model(array $row)
    {
        $this->rowCount++;
        
        return new Timeslot([
            'day_of_week' => $row['day_of_week'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'slot_code' => $row['slot_code']
        ]);
    }

    public function rules(): array
    {
        return [
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'slot_code' => 'required|string|unique:timeslots,slot_code'
        ];
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }
}
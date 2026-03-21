<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Timeslot;

class TimeslotSeeder extends Seeder
{
    public function run()
    {
        $days = ['Monday','Tuesday','Wednesday','Thursday','Friday'];

        $times = [
            ['start' => '02:00', 'end' => '03:50'],
            ['start' => '04:00', 'end' => '04:50'],
            ['start' => '05:00', 'end' => '05:50'],
            ['start' => '07:30', 'end' => '09:10'],
            ['start' => '09:20', 'end' => '10:10'],
            ['start' => '10:20', 'end' => '11:10'],
        ];

        foreach ($days as $day) {

            foreach ($times as $time) {

                $slotCode =
                    strtoupper(substr($day,0,3)) . '_' .
                    str_replace(':','',$time['start']) . '_' .
                    str_replace(':','',$time['end']);

                Timeslot::updateOrCreate(
                    ['slot_code' => $slotCode],
                    [
                        'day_of_week' => $day,
                        'start_time' => $time['start'],
                        'end_time' => $time['end'],
                        'slot_code' => $slotCode
                    ]
                );
            }
        }
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Timeslot;

class TimeslotSeeder extends Seeder
{
    public function run()
    {
        $weekdays = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
        $weekendDays = ['Saturday','Sunday'];

        // ==========================================================
        // REGULAR STUDENT TIMESLOTS (Monday-Friday only)
        // Session 1: 2:00 PM - 6:00 PM (14:00-18:00)
        // Session 2: 7:30 PM - 11:30 PM (19:30-23:30)
        // ==========================================================
        $regularMorning = [
            ['start' => '14:00', 'end' => '15:00'],
            ['start' => '15:00', 'end' => '16:00'],
            ['start' => '16:00', 'end' => '17:00'],
            ['start' => '17:00', 'end' => '18:00'],
        ];

        $regularAfternoon = [
            ['start' => '19:30', 'end' => '20:30'],
            ['start' => '20:30', 'end' => '21:30'],
            ['start' => '21:30', 'end' => '22:30'],
            ['start' => '22:30', 'end' => '23:30'],
        ];

        // Seed REGULAR student timeslots with REG_ prefix
        foreach ($weekdays as $day) {
            $this->upsertSlotsForDay($day, array_merge($regularMorning, $regularAfternoon), 'REG');
        }

        // ==========================================================
        // WEEKEND STUDENT TIMESLOTS
        // Weekday (Mon-Fri): 11:30 AM - 2:00 PM (11:30-14:00)
        // Weekend (Sat-Sun): 2:00 PM - 6:00 PM (14:00-18:00)
        //                    7:30 PM - 11:30 PM (19:30-23:30)
        // ==========================================================
        $weekdayEvening = [
            ['start' => '11:30', 'end' => '12:15'],
            ['start' => '12:15', 'end' => '13:00'],
            ['start' => '13:00', 'end' => '14:00'],
        ];

        $weekendMorning = [
            ['start' => '14:00', 'end' => '15:00'],
            ['start' => '15:00', 'end' => '16:00'],
            ['start' => '16:00', 'end' => '17:00'],
            ['start' => '17:00', 'end' => '18:00'],
        ];

        $weekendAfternoon = [
            ['start' => '19:30', 'end' => '20:30'],
            ['start' => '20:30', 'end' => '21:30'],
            ['start' => '21:30', 'end' => '22:30'],
            ['start' => '22:30', 'end' => '23:30'],
        ];

        // Seed WEEKEND student weekday timeslots with WKE_ prefix
        foreach ($weekdays as $day) {
            $this->upsertSlotsForDay($day, $weekdayEvening, 'WKE');
        }

        // Seed WEEKEND student weekend timeslots with WKE_ prefix
        foreach ($weekendDays as $day) {
            $this->upsertSlotsForDay($day, array_merge($weekendMorning, $weekendAfternoon), 'WKE');
        }
    }

    private function upsertSlotsForDay(string $day, array $slots, string $type = 'REG'): void
    {
        foreach ($slots as $time) {
            // Slot code format: TYPE_DAY_STARTTIME_ENDTIME
            // Examples: REG_MON_1400_1500, WKE_SAT_1400_1500
            $slotCode =
                $type . '_' .
                strtoupper(substr($day, 0, 3)) . '_' .
                str_replace(':', '', $time['start']) . '_' .
                str_replace(':', '', $time['end']);

            Timeslot::updateOrCreate(
                ['slot_code' => $slotCode],
                [
                    'day_of_week' => $day,
                    'start_time' => $time['start'],
                    'end_time' => $time['end'],
                    'slot_code' => $slotCode,
                ]
            );
        }
    }
}

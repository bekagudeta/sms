<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Timeslot;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $sections = Section::all();
        $rooms = Room::all();
        $timeslots = Timeslot::all();

        foreach ($sections as $section) {
            // Create 1-3 schedules per section (different days/times)
            $numSchedules = rand(1, 3);
            $selectedTimeslots = $timeslots->random($numSchedules);

            foreach ($selectedTimeslots as $timeslot) {
                if (Schedule::where('section_id', $section->id)
                    ->where('timeslot_id', $timeslot->id)
                    ->exists()) {
                    continue;
                }

                // Find an available room for this timeslot
                $availableRoom = $rooms->first(function ($room) use ($timeslot) {
                    return ! Schedule::where('room_id', $room->id)
                        ->where('timeslot_id', $timeslot->id)
                        ->exists();
                });

                if ($availableRoom) {
                    Schedule::updateOrCreate(
                        [
                            'section_id' => $section->id,
                            'timeslot_id' => $timeslot->id,
                        ],
                        [
                            'room_id' => $availableRoom->id,
                        ]
                    );
                }
            }
        }
    }
}

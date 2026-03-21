<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;
use Faker\Factory as Faker;

class RoomSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        $buildings = [
            'Main Building',
            'Science Building',
            'Engineering Building',
            'Administration Block',
            'Auditorium'
        ];

        $types = ['lecture', 'lab', 'conference'];

        for ($i = 1; $i <= 30; $i++) {

            Room::firstOrCreate([
                'room_code' => 'R' . str_pad($i, 3, '0', STR_PAD_LEFT),
            ], [
                'building' => $faker->randomElement($buildings),
                'floor' => $faker->numberBetween(0, 5),
                'capacity' => $faker->numberBetween(15, 120),
                'type' => $faker->randomElement($types),
            ]);
        }
    }
}
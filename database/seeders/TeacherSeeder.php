<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Teacher;
use App\Models\Department;
use Faker\Factory as Faker;

class TeacherSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        // get department ids
        $departments = Department::pluck('id')->toArray();

        for ($i = 1; $i <= 30; $i++) {

            Teacher::firstOrCreate([
                'teacher_id' => 'T' . str_pad($i, 3, '0', STR_PAD_LEFT),
            ], [
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->phoneNumber,
                'department_id' => $faker->randomElement($departments),
            ]);
        }
    }
}
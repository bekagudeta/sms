<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\Department;
use Faker\Factory as Faker;

class StudentSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        $departments = Department::pluck('id')->toArray();

        for ($i = 1; $i <= 50; $i++) {

            Student::firstOrCreate([
                'student_id' => 'STU' . str_pad($i, 3, '0', STR_PAD_LEFT),
            ], [
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->phoneNumber,
                'department_id' => $faker->randomElement($departments),
                'semester' => $faker->numberBetween(1, 8),
                'enrollment_date' => $faker->date('Y-m-d'),
            ]);
        }
    }
}
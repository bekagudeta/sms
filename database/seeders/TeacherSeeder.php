<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class TeacherSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        // get department ids
        $departments = Department::pluck('id')->toArray();

        for ($i = 1; $i <= 5; $i++) {
            // Create a user for each teacher
            $user = User::firstOrCreate(
                ['email' => 'teacher' . $i . '@university.edu'],
                [
                    'name' => $faker->name,
                    'password' => Hash::make('password'),
                    'must_change_password' => false
                ]
            );
            // Don't assign role for now - roles need to be properly seeded first

            // Create teacher record linked to user
            Teacher::firstOrCreate(
                [
                    'user_id' => $user->id,
                ], 
                [
                    'teacher_id' => 'T' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'first_name' => $faker->firstName,
                    'last_name' => $faker->lastName,
                    'email' => $user->email,
                    'phone' => $faker->phoneNumber,
                    'department_id' => $faker->randomElement($departments),
                    'max_hours_per_week' => rand(15, 25),
                ]
            );
        }
    }
}
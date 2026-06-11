<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class StudentSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        $departments = Department::pluck('id')->toArray();

        for ($i = 1; $i <= 5; $i++) {
            $email = $faker->unique()->safeEmail;

            // Create a user first
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $faker->name,
                    'password' => Hash::make('password'),
                    'must_change_password' => false
                ]
            );

            $student = Student::firstOrCreate(
                [
                    'student_id' => 'STU' . str_pad($i, 3, '0', STR_PAD_LEFT),
                ], 
                [
                    'user_id' => $user->id,
                    'first_name' => $faker->firstName,
                    'last_name' => $faker->lastName,
                    'email' => $email,
                    'phone' => $faker->phoneNumber,
                    'department_id' => $faker->randomElement($departments),
                    'level' => $faker->randomElements(['undergraduate', 'postgraduate'])[0],
                    'academic_section' => $faker->randomElement(['SE-3A', 'SE-3B', 'CS-2A', 'CS-2B']),
                    'student_type' => $faker->randomElement(['regular', 'weekend']),
                    'enrollment_date' => $faker->date('Y-m-d'),
                ]
            );
        }
    }
}

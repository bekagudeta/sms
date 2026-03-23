<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Course;
use App\Models\Department;
use App\Models\CourseOffering;
use App\Models\Semester;
use Faker\Factory as Faker;

class CourseSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        $departments = Department::all();
        $semesters = Semester::pluck('id')->toArray();

        $courseNames = [
            'Programming Fundamentals',
            'Data Structures',
            'Algorithms',
            'Database Systems',
            'Computer Networks',
            'Operating Systems',
            'Software Engineering',
            'Artificial Intelligence',
            'Machine Learning',
            'Web Development'
        ];

        $levels = ['undergraduate', 'graduate'];

        foreach ($departments as $department) {

            for ($i = 1; $i <= 5; $i++) {

                $code = $department->code . (100 + $i);

                $course = Course::updateOrCreate(
                    ['course_code' => $code],
                    [
                        'course_code' => $code,
                        'course_name' => $faker->randomElement($courseNames),
                        'credits' => $faker->numberBetween(2,4),
                        'hours_per_week' => $faker->numberBetween(2,4),
                        'department_id' => $department->id,
                        'level' => $faker->randomElement($levels)
                    ]
                );

                // Create course offerings for each semester
                foreach ($semesters as $semesterId) {
                    CourseOffering::updateOrCreate(
                        [
                            'course_id' => $course->id,
                            'semester_id' => $semesterId,
                        ],
                        [
                            'expected_students' => $faker->numberBetween(20, 50),
                        ]
                    );
                }
            }
        }
    }
}
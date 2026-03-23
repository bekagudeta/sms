<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CourseOffering;
use App\Models\Course;
use App\Models\Semester;

class CourseOfferingSeeder extends Seeder
{
    public function run()
    {
        $courses = Course::all();
        $semesters = Semester::all();

        foreach ($courses as $course) {
            foreach ($semesters as $semester) {
                CourseOffering::create([
                    'course_id' => $course->id,
                    'semester_id' => $semester->id,
                    'expected_students' => rand(20, 60),
                ]);
            }
        }
    }
}

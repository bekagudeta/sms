<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;

class EnrollmentSeeder extends Seeder
{
    public function run()
    {
        $sections = Section::all();
        $students = Student::all();

        foreach ($sections as $section) {
            // Enroll 60-80% of capacity
            $numEnrollments = rand(
                (int)($section->capacity * 0.6),
                (int)($section->capacity * 0.8)
            );
            
            $enrolledStudents = $students->random(min($numEnrollments, $students->count()));
            
            foreach ($enrolledStudents as $student) {
                Enrollment::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'section_id' => $section->id,
                    ],
                    []
                );
            }
        }
    }
}

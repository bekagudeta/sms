<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\SectionTeacher;

class SectionTeacherSeeder extends Seeder
{
    public function run()
    {
        $sections = Section::all();
        $teachers = Teacher::all();

        foreach ($sections as $section) {
            // Assign 1-2 teachers per section
            $numTeachers = rand(1, 2);
            $assignedTeachers = $teachers->random($numTeachers);
            
            foreach ($assignedTeachers as $teacher) {
                SectionTeacher::updateOrCreate(
                    [
                        'section_id' => $section->id,
                        'teacher_id' => $teacher->id,
                    ],
                    []
                );
            }
        }
    }
}

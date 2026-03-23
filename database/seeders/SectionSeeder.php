<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Section;
use App\Models\CourseOffering;

class SectionSeeder extends Seeder
{
    public function run()
    {
        $courseOfferings = CourseOffering::all();

        foreach ($courseOfferings as $offering) {
            // Create 1-3 sections per course offering
            $numSections = rand(1, 3);
            $sectionNames = ['A', 'B', 'C'];
            
            for ($i = 0; $i < $numSections; $i++) {
                Section::updateOrCreate(
                    [
                        'course_offering_id' => $offering->id,
                        'section_name' => $sectionNames[$i],
                    ],
                    [
                        'capacity' => rand(25, 40),
                    ]
                );
            }
        }
    }
}

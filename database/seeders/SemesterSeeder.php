<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Semester;

class SemesterSeeder extends Seeder
{
    public function run()
    {
        $semesters = [
            ['name' => '1st Semester', 'code' => 'SEM1', 'start_date' => '2023-09-01', 'end_date' => '2023-12-31'],
            ['name' => '2nd Semester', 'code' => 'SEM2', 'start_date' => '2024-01-15', 'end_date' => '2024-05-31'],
        ];

        foreach ($semesters as $sem) {
            Semester::updateOrCreate(
                ['code' => $sem['code']],
                $sem
            );
        }
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['code' => 'CS', 'name' => 'Computer Science'],
            ['code' => 'IT', 'name' => 'Information Technology'],
            ['code' => 'EE', 'name' => 'Electrical Engineering'],
            ['code' => 'ME', 'name' => 'Mechanical Engineering'],
            ['code' => 'CE', 'name' => 'Civil Engineering'],
            ['code' => 'BA', 'name' => 'Business Administration'],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(
                ['code' => $dept['code']],
                ['name' => $dept['name']]
            );
        }
    }
}
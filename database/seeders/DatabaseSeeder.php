<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            RoleSeeder::class,
            DepartmentSeeder::class,
            SemesterSeeder::class,
            RoomSeeder::class,
            TimeslotSeeder::class,
            TeacherSeeder::class,
            CourseSeeder::class,
            StudentSeeder::class,
            SectionSeeder::class,
            SectionTeacherSeeder::class,
            EnrollmentSeeder::class,
            ScheduleSeeder::class,
            UserSeeder::class,
        ]);
    }
}
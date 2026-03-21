<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\Department;

class UpdateSchedulingDataSeeder extends Seeder
{
    public function run()
    {
        // Update courses with missing fields
        $courses = Course::all();
        foreach ($courses as $course) {
            $course->student_count = rand(20, 60);
            $course->required_room_type = $this->getRoomTypeForCourse($course);
            $course->save();
        }

        // Update teachers with specialization
        $teachers = Teacher::all();
        foreach ($teachers as $teacher) {
            if (!$teacher->specialization) {
                $department = Department::find($teacher->department_id);
                $teacher->specialization = $department ? $department->name : 'General';
                $teacher->save();
            }
        }

        $this->command->info('Scheduling data updated successfully!');
    }

    private function getRoomTypeForCourse($course)
    {
        $courseName = strtolower($course->course_name);
        
        // Determine room type based on course name patterns
        if (strpos($courseName, 'lab') !== false || 
            strpos($courseName, 'programming') !== false || 
            strpos($courseName, 'computer') !== false ||
            strpos($courseName, 'software') !== false ||
            strpos($courseName, 'web') !== false ||
            strpos($courseName, 'database') !== false ||
            strpos($courseName, 'operating systems') !== false ||
            strpos($courseName, 'algorithms') !== false ||
            strpos($courseName, 'data structures') !== false) {
            return 'lab';
        }
        
        if (strpos($courseName, 'seminar') !== false || 
            strpos($courseName, 'workshop') !== false) {
            return 'seminar';
        }
        
        if (strpos($courseName, 'conference') !== false) {
            return 'conference';
        }
        
        // Distribute more courses to lab rooms since we have more of them
        $labCourses = ['mathematics', 'physics', 'chemistry', 'biology', 'statistics', 'calculus'];
        foreach ($labCourses as $labCourse) {
            if (strpos($courseName, $labCourse) !== false) {
                return 'lab';
            }
        }
        
        return 'lecture'; // Default to lecture
    }
}

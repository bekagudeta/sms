<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Teacher;
use App\Support\TeacherScope;
use Illuminate\Support\Collection;

class TeacherStudentService
{
    public function studentsForTeacher(Teacher $teacher, ?int $semesterId = null): Collection
    {
        $query = Student::query()
            ->whereHas('enrollments.section.teachers', function ($q) use ($teacher) {
                TeacherScope::wherePrimaryKey($q, $teacher->id);
            });

        if ($semesterId) {
            $query->whereHas('enrollments.section.courseOffering', function ($q) use ($semesterId) {
                $q->where('semester_id', $semesterId);
            });
        }

        return $query
            ->with(['department'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->unique('id')
            ->values()
            ->map(function (Student $student) use ($teacher, $semesterId) {
                $courses = $student->enrollments()
                    ->whereHas('section.teachers', fn ($q) => TeacherScope::wherePrimaryKey($q, $teacher->id))
                    ->when($semesterId, fn ($q) => $q->whereHas('section.courseOffering', fn ($cq) => $cq->where('semester_id', $semesterId)))
                    ->with(['section.courseOffering.course', 'section.courseOffering.semester'])
                    ->get()
                    ->map(function ($enrollment) {
                        $course = $enrollment->section?->courseOffering?->course;

                        return [
                            'course_code' => $course?->course_code,
                            'course_name' => $course?->course_name,
                            'section_name' => $enrollment->section?->section_name,
                            'semester' => $enrollment->section?->courseOffering?->semester?->name,
                        ];
                    })
                    ->filter(fn ($row) => $row['course_code'])
                    ->values()
                    ->all();

                return [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'phone' => $student->phone,
                    'department' => $student->department?->name,
                    'year_level' => \App\Support\ScheduleDisplay::formatYearLevel($student->level)
                        ?? \App\Support\ScheduleDisplay::yearLevelFromAcademicSection($student->academic_section),
                    'academic_section' => $student->academic_section,
                    'status' => $student->status,
                    'courses' => $courses,
                ];
            });
    }
}

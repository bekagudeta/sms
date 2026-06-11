<?php

namespace App\Exports;

use App\Models\Schedule;
use App\Support\ScheduleDisplay;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ScheduleExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $semesterId;

    public function __construct($semesterId = null)
    {
        $this->semesterId = $semesterId;
    }

    public function collection()
    {
        $query = Schedule::with([
            'section.courseOffering.course.department',
            'section.courseOffering.semester',
            'section.teachers.user',
            'section.enrollments.student',
            'room',
            'timeslot',
        ]);
        
        if ($this->semesterId) {
            $query->whereHas('section.courseOffering', function($q) {
                $q->where('semester_id', $this->semesterId);
            });
        }
        
        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Academic Year',
            'Department',
            'Year Level',
            'Semester',
            'Course Code',
            'Course Name',
            'Instructor',
            'Classroom',
            'Day',
            'Time',
            'Section',
            'Student Type',
            'Max Students',
            'Status',
        ];
    }

    public function map($schedule): array
    {
        $display = ScheduleDisplay::for($schedule);

        return [
            $display['academic_year'] ?? 'N/A',
            $display['department'] ?? 'N/A',
            $display['year_level'] ?? 'N/A',
            $display['semester'] ?? 'N/A',
            $display['course_code'] ?? 'N/A',
            $display['course_name'] ?? 'N/A',
            $display['instructor'] ?? 'Not assigned',
            $display['classroom'] ?? 'N/A',
            $display['day'] ?? 'Not set',
            $display['time'] ?? 'Not set',
            $display['section'] ?? 'N/A',
            $display['student_type'] ?? 'Regular',
            $schedule->section?->capacity ?? 'N/A',
            'Scheduled',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
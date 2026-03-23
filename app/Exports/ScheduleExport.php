<?php

namespace App\Exports;

use App\Models\Schedule;
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
        $query = Schedule::with(['section.courseOffering.course', 'section.teachers', 'room', 'timeslot', 'section.courseOffering.semester']);
        
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
            'Course Code',
            'Course Name',
            'Teacher',
            'Room',
            'Day',
            'Time',
            'Semester',
            'Section',
            'Max Students',
            'Status'
        ];
    }

    public function map($schedule): array
    {
        $course = $schedule->section?->courseOffering?->course;
        $teacher = $schedule->section?->teachers?->first();
        $semester = $schedule->section?->courseOffering?->semester;
        
        return [
            $course?->course_code ?? 'N/A',
            $course?->course_name ?? 'N/A',
            $teacher?->user?->name ?? 'Not assigned',
            $schedule->room?->room_code ?? 'N/A',
            $schedule->timeslot?->day_of_week ?? 'Not set',
            $schedule->timeslot?->start_time && $schedule->timeslot?->end_time ? 
                $schedule->timeslot->start_time . ' - ' . $schedule->timeslot->end_time : 
                'Not set',
            $semester?->name ?? 'N/A',
            $schedule->section?->section_name ?? 'N/A',
            $schedule->section?->capacity ?? 'N/A',
            'Scheduled'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
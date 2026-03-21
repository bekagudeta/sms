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
        $query = Schedule::with(['course', 'teacher', 'room', 'semester']);
        
        if ($this->semesterId) {
            $query->where('semester_id', $this->semesterId);
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
        return [
            $schedule->course->course_code,
            $schedule->course->course_name,
            $schedule->teacher->full_name,
            $schedule->room->room_code,
            $schedule->day,
            $schedule->start_time && $schedule->end_time ? 
                $schedule->start_time . ' - ' . $schedule->end_time : 
                'Not set',
            $schedule->semester->name,
            $schedule->section,
            $schedule->max_students,
            ucfirst($schedule->status)
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
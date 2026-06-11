<?php

namespace App\Exports;

use App\Models\Student;
use App\Support\ScheduleDisplay;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Student::with('department')->get();
    }

    public function headings(): array
    {
        return [
            'Student ID',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Department',
            'Year Level',
            'Academic Section',
            'Student Type',
            'Status',
            'Enrollment Date',
        ];
    }

    public function map($student): array
    {
        return [
            $student->student_id,
            $student->first_name,
            $student->last_name,
            $student->email,
            $student->phone,
            $student->department?->name ?? '',
            ScheduleDisplay::formatYearLevel($student->level)
                ?? ScheduleDisplay::yearLevelFromAcademicSection($student->academic_section)
                ?? '',
            $student->academic_section ?? '',
            ucfirst($student->student_type ?? 'regular'),
            $student->status ?? '',
            $student->enrollment_date?->format('Y-m-d') ?? '',
        ];
    }
}
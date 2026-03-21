<?php

namespace App\Exports;

use App\Models\Teacher;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TeachersExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Teacher::with('department')->get();
    }

    public function headings(): array
    {
        return [
            'Teacher ID',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Department',
            'Qualification',
            'Max Hours/Week'
        ];
    }

    public function map($teacher): array
    {
        return [
            $teacher->teacher_id,
            $teacher->first_name,
            $teacher->last_name,
            $teacher->email,
            $teacher->phone,
            $teacher->department->name,
            $teacher->qualification,
            $teacher->max_hours_per_week
        ];
    }
}
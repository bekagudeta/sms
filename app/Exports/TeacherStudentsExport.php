<?php

namespace App\Exports;

use App\Models\Student;
use App\Support\ScheduleDisplay;
use App\Support\TeacherScope;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TeacherStudentsExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(
        protected int $teacherId,
        protected ?int $semesterId = null
    ) {}

    public function collection()
    {
        $query = Student::query()
            ->whereHas('enrollments.section.teachers', function ($q) {
                TeacherScope::wherePrimaryKey($q, $this->teacherId);
            })
            ->with(['department']);

        if ($this->semesterId) {
            $query->whereHas('enrollments.section.courseOffering', function ($q) {
                $q->where('semester_id', $this->semesterId);
            });
        }

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->unique('id')
            ->values();
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
            'Status',
            'Enrolled Courses',
        ];
    }

    public function map($student): array
    {
        $courses = $student->enrollments()
            ->whereHas('section.teachers', fn ($q) => TeacherScope::wherePrimaryKey($q, $this->teacherId))
            ->when($this->semesterId, fn ($q) => $q->whereHas('section.courseOffering', fn ($cq) => $cq->where('semester_id', $this->semesterId)))
            ->with(['section.courseOffering.course'])
            ->get()
            ->map(fn ($e) => $e->section?->courseOffering?->course?->course_code)
            ->filter()
            ->unique()
            ->implode(', ');

        return [
            $student->student_id,
            $student->first_name,
            $student->last_name,
            $student->email,
            $student->phone ?? '',
            $student->department?->name ?? '',
            ScheduleDisplay::formatYearLevel($student->level)
                ?? ScheduleDisplay::yearLevelFromAcademicSection($student->academic_section)
                ?? '',
            $student->academic_section ?? '',
            $student->status ?? '',
            $courses,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Schedule;
use App\Support\ScheduleDisplay;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;

/**
 * Professional Schedule Export Service
 * 
 * Generates beautifully formatted schedule exports in PDF and Excel formats.
 * Includes student type information, visual formatting, and detailed metadata.
 * 
 * @category Services
 * @package App\Services
 */
class ProfessionalScheduleExporter
{
    private Collection $schedules;
    private array $displayData;
    private string $generatedAt;

    public function __construct()
    {
        $this->generatedAt = now()->format('Y-m-d H:i:s');
    }

    /**
     * Set schedules to export
     *
     * @param Collection $schedules
     * @return self
     */
    public function setSchedules(Collection $schedules): self
    {
        $this->schedules = $schedules;
        $this->prepareDisplayData();
        return $this;
    }

    /**
     * Prepare display data for all schedules
     */
    private function prepareDisplayData(): void
    {
        $this->displayData = $this->schedules->map(function ($schedule) {
            return ScheduleDisplay::for($schedule);
        })->toArray();
    }

    /**
     * Export to professional Excel format with formatting
     *
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
    public function toExcel(string $filename = 'schedules.xlsx')
    {
        return Excel::download(
            new ScheduleExportSheet($this->displayData),
            $filename,
            'Xlsx'
        );
    }

    /**
     * Export to PDF with professional formatting
     *
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
    public function toPdf(string $filename = 'schedules.pdf')
    {
        $data = [
            'schedules' => $this->displayData,
            'generated_at' => $this->generatedAt,
            'total_schedules' => count($this->displayData),
            'regular_count' => collect($this->displayData)->where('student_type', 'Regular')->count(),
            'weekend_count' => collect($this->displayData)->where('student_type', 'Weekend')->count(),
        ];

        $pdf = Pdf::loadView('exports.schedule-pdf', $data)
            ->setOption('margin-top', 15)
            ->setOption('margin-bottom', 15)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10)
            ->setOption('dpi', 300);

        return $pdf->download($filename);
    }

    /**
     * Export to CSV with student type information
     *
     * @param string $filename
     * @return \Illuminate\Http\Response
     */
    public function toCsv(string $filename = 'schedules.csv')
    {
        return Excel::download(
            new ScheduleExportCsv($this->displayData),
            $filename,
            'Csv'
        );
    }

    /**
     * Get formatted schedule report for display
     *
     * @param string $format 'array' or 'collection'
     * @return array|Collection
     */
    public function getFormattedReport(string $format = 'array')
    {
        $data = collect($this->displayData);

        $report = [
            'metadata' => [
                'generated_at' => $this->generatedAt,
                'total_schedules' => $data->count(),
                'by_student_type' => $data->groupBy('student_type')
                    ->map->count()
                    ->toArray(),
                'by_day' => $data->groupBy('day')
                    ->map->count()
                    ->toArray(),
                'by_department' => $data->groupBy('department_name')
                    ->map->count()
                    ->toArray(),
            ],
            'schedules' => $data->toArray()
        ];

        return $format === 'collection' ? collect($report) : $report;
    }

    /**
     * Generate summary statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $data = collect($this->displayData);

        $regularSchedules = $data->where('student_type', 'Regular');
        $weekendSchedules = $data->where('student_type', 'Weekend');

        return [
            'total_schedules' => $data->count(),
            'regular_schedules' => $regularSchedules->count(),
            'weekend_schedules' => $weekendSchedules->count(),
            'percentage_weekend' => round(($weekendSchedules->count() / $data->count()) * 100, 2),
            'days_covered' => $data->pluck('day')->unique()->count(),
            'total_students' => $data->pluck('section')->unique()->count(),
            'timeslots_used' => $data->pluck('timeslot')->unique()->count(),
            'distribution_by_day' => $data->groupBy('day')
                ->map(function ($group) {
                    return [
                        'total' => $group->count(),
                        'regular' => $group->where('student_type', 'Regular')->count(),
                        'weekend' => $group->where('student_type', 'Weekend')->count(),
                    ];
                })
                ->toArray(),
        ];
    }

    /**
     * Generate comparison report between schedule types
     *
     * @return array
     */
    public function getComparison(): array
    {
        $data = collect($this->displayData);
        $regular = $data->where('student_type', 'Regular');
        $weekend = $data->where('student_type', 'Weekend');

        return [
            'regular_statistics' => [
                'total' => $regular->count(),
                'days' => $regular->pluck('day')->unique()->values()->toArray(),
                'timeslots' => $regular->pluck('session')->unique()->values()->toArray(),
                'average_students_per_slot' => round($regular->avg('max_students'), 2),
                'total_student_hours' => $regular->count() * 0.5, // Assuming 30-min slots
            ],
            'weekend_statistics' => [
                'total' => $weekend->count(),
                'days' => $weekend->pluck('day')->unique()->values()->toArray(),
                'timeslots' => $weekend->pluck('session')->unique()->values()->toArray(),
                'average_students_per_slot' => round($weekend->avg('max_students'), 2),
                'total_student_hours' => $weekend->count() * 0.5,
            ],
            'resource_allocation' => [
                'rooms_for_regular' => $regular->pluck('room')->unique()->count(),
                'rooms_for_weekend' => $weekend->pluck('room')->unique()->count(),
                'teachers_for_regular' => $regular->pluck('teacher_name')->unique()->count(),
                'teachers_for_weekend' => $weekend->pluck('teacher_name')->unique()->count(),
            ]
        ];
    }
}

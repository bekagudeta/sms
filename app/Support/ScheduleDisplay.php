<?php

namespace App\Support;

use App\Models\Schedule;
use App\Models\Section;

class ScheduleDisplay
{
    public static function for(Schedule $schedule): array
    {
        $course = $schedule->course ?? $schedule->section?->courseOffering?->course;
        $semester = $schedule->semester ?? $schedule->section?->courseOffering?->semester;
        $timeslot = $schedule->timeslot;
        $teacher = $schedule->teacher ?? $schedule->section?->teachers?->first();

        return [
            'academic_year' => AcademicYear::forSemester($semester),
            'department' => $course?->department?->name ?? $course?->department?->code,
            'year_level' => static::yearLevelForSection($schedule->section),
            'semester' => $semester?->name,
            'course_code' => $course?->course_code,
            'course_name' => $course?->course_name,
            'instructor' => $schedule->teacher_name
                ?? ($teacher?->full_name ?: $teacher?->user?->name),
            'classroom' => $schedule->room?->room_code,
            'day' => $timeslot?->day_of_week,
            'time' => static::formatTimeRange($timeslot?->start_time, $timeslot?->end_time),
            'section' => $schedule->section?->section_name,
        ];
    }

    public static function yearLevelForSection(?Section $section): ?string
    {
        if (! $section) {
            return null;
        }

        if ($section->relationLoaded('enrollments')) {
            $levels = $section->enrollments
                ->map(fn ($enrollment) => $enrollment->student?->level)
                ->filter()
                ->unique()
                ->values();
        } else {
            $levels = $section->students()
                ->whereNotNull('level')
                ->where('level', '!=', '')
                ->distinct()
                ->pluck('level');
        }

        if ($levels->count() === 1) {
            return static::formatYearLevel($levels->first());
        }

        if ($levels->count() > 1) {
            return $levels->map(fn ($level) => static::formatYearLevel($level))->sort()->implode(', ');
        }

        return static::yearLevelFromAcademicSection(
            $section->students()->value('academic_section')
        );
    }

    public static function formatYearLevel(?string $level): ?string
    {
        if ($level === null || trim($level) === '') {
            return null;
        }

        $level = trim($level);

        if (ctype_digit($level)) {
            return 'Year '.$level;
        }

        return $level;
    }

    public static function yearLevelFromAcademicSection(?string $academicSection): ?string
    {
        if (! $academicSection || ! preg_match('/-(\d)/', $academicSection, $matches)) {
            return null;
        }

        return 'Year '.$matches[1];
    }

    public static function formatTimeRange(?string $start, ?string $end): ?string
    {
        if (! $start || ! $end) {
            return null;
        }

        return trim($start).' - '.trim($end);
    }
}

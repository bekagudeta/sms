<?php

namespace App\Support;

class StudentScheduleRules
{
    public const DEFAULT_TYPE = 'regular';
    public const MIXED_TYPE = 'mixed';

    public static function normalizeStudentType(mixed $value): string
    {
        $type = strtolower(trim((string) ($value ?? self::DEFAULT_TYPE)));
        $type = str_replace(['-', ' '], '_', $type);

        $aliases = [
            'regular_student' => 'regular',
            'regular_students' => 'regular',
            'weekend_student' => 'weekend',
            'weekend_students' => 'weekend',
            'week_end' => 'weekend',
        ];

        $type = $aliases[$type] ?? $type;
        $allowed = config('scheduling.student_types', ['regular', 'weekend']);

        return in_array($type, $allowed, true) ? $type : self::DEFAULT_TYPE;
    }

    public static function sectionStudentTypes($section): array
    {
        $section->loadMissing('enrollments.student');

        return $section->enrollments
            ->map(fn ($enrollment) => self::normalizeStudentType($enrollment->student?->student_type))
            ->unique()
            ->values()
            ->all();
    }

    public static function sectionStudentType($section): string
    {
        $types = self::sectionStudentTypes($section);

        if (count($types) === 0) {
            return self::DEFAULT_TYPE;
        }

        return count($types) === 1 ? $types[0] : self::MIXED_TYPE;
    }

    public static function timeslotAllowedForType(string $studentType, $timeslot): bool
    {
        if ($studentType === self::MIXED_TYPE) {
            return false;
        }

        $studentType = self::normalizeStudentType($studentType);

        if (($timeslot->student_type ?? null) !== null && (string) $timeslot->student_type !== '') {
            if ($studentType === self::normalizeStudentType((string) $timeslot->student_type)) {
                return true;
            }

            return false;
        }

        $rules = config("scheduling.student_type_timeslots.{$studentType}", []);

        foreach ($rules as $rule) {
            if (!in_array($timeslot->day_of_week, $rule['days'] ?? [], true)) {
                continue;
            }

            foreach ($rule['sessions'] ?? [] as $session) {
                $window = config("scheduling.session_windows.{$session}");
                if ($window && self::timeslotFitsWindow($timeslot, $window)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function allowedTeachingHoursForType(string $studentType, iterable $timeslots): float
    {
        $hours = 0.0;

        foreach ($timeslots as $timeslot) {
            if (self::timeslotAllowedForType($studentType, $timeslot)) {
                $hours += TimeslotDuration::teachingHours($timeslot);
            }
        }

        return round($hours, 4);
    }

    public static function timeslotFitsWindow($timeslot, array $window): bool
    {
        [$slotStart, $slotEnd] = self::intervalForTimes($timeslot->start_time, $timeslot->end_time);
        [$windowStart, $windowEnd] = self::intervalForTimes($window['start'] ?? '00:00', $window['end'] ?? '00:00');

        return $slotStart >= $windowStart && $slotEnd <= $windowEnd;
    }

    public static function timeslotsOverlap($first, $second): bool
    {
        if (!$first || !$second || $first->day_of_week !== $second->day_of_week) {
            return false;
        }

        [$firstStart, $firstEnd] = self::intervalForTimes($first->start_time, $first->end_time);
        [$secondStart, $secondEnd] = self::intervalForTimes($second->start_time, $second->end_time);

        return $firstStart < $secondEnd && $secondStart < $firstEnd;
    }

    public static function describeTypeRules(string $studentType): string
    {
        return match (self::normalizeStudentType($studentType)) {
            'weekend' => 'Mon-Fri evening plus Saturday/Sunday morning and afternoon sessions',
            default => 'Mon-Fri morning and afternoon sessions',
        };
    }

    protected static function intervalForTimes(mixed $start, mixed $end): array
    {
        $startMinutes = self::minutesForTime($start);
        $endMinutes = self::minutesForTime($end);

        if ($endMinutes <= $startMinutes) {
            $endMinutes += 1440;
        }

        return [$startMinutes, $endMinutes];
    }

    protected static function minutesForTime(mixed $time): int
    {
        $value = trim((string) $time);

        if (preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
            return ((int) $matches[1] * 60) + (int) $matches[2];
        }

        return 0;
    }
}

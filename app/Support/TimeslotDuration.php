<?php

namespace App\Support;

use Carbon\Carbon;

class TimeslotDuration
{
    /**
     * Actual length of a timeslot in minutes (from start_time → end_time).
     */
    public static function minutes($timeslot): int
    {
        $start = Carbon::parse(static::normalizeTime($timeslot->start_time ?? '00:00:00'));
        $end   = Carbon::parse(static::normalizeTime($timeslot->end_time ?? '00:00:00'));

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return (int) $start->diffInMinutes($end);
    }

    /**
     * Teaching hours contributed by one assignment in this timeslot.
     * Uses exact duration (e.g. 60 min = 1.0 h, 110 min ≈ 1.83 h — never rounded up).
     */
    public static function teachingHours($timeslot): float
    {
        return static::minutes($timeslot) / 60;
    }

    public static function totalWeeklyTeachingHours(iterable $timeslots): float
    {
        $total = 0.0;
        foreach ($timeslots as $timeslot) {
            $total += static::teachingHours($timeslot);
        }

        return round($total, 4);
    }

    protected static function normalizeTime(string $time): string
    {
        $time = trim($time);

        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return $time.':00';
        }

        return $time;
    }
}

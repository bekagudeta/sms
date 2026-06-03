<?php

namespace App\Support;

use App\Models\Semester;
use Carbon\Carbon;

class AcademicYear
{
    public static function forSemester(?Semester $semester): ?string
    {
        if (! $semester) {
            return null;
        }

        if (! empty($semester->academic_year)) {
            return (string) $semester->academic_year;
        }

        if ($semester->start_date) {
            return Carbon::parse($semester->start_date)->format('Y');
        }

        if (preg_match('/\b(20\d{2})\b/', (string) $semester->name, $matches)) {
            return $matches[1];
        }

        if (preg_match('/(20\d{2})/', (string) $semester->code, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

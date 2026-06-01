<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Section;
use App\Models\Semester;

class CourseSectionResolver
{
    /**
     * Resolve a course section (class offering) — not a student's academic cohort.
     *
     * Preferred: course_code + semester_code + section_name
     * Legacy: section_code (e.g. CS101_F2024_A) or numeric section_id
     */
    public static function resolve(
        ?string $courseCode = null,
        ?string $semesterCode = null,
        ?string $sectionName = null,
        ?string $sectionCode = null,
        ?string $sectionId = null,
    ): ?Section {
        $courseCode = trim((string) $courseCode);
        $semesterCode = trim((string) $semesterCode);
        $sectionName = trim((string) $sectionName);
        $sectionCode = trim((string) $sectionCode);
        $sectionId = trim((string) $sectionId);

        if ($courseCode !== '' && $semesterCode !== '' && $sectionName !== '') {
            return self::findByOffering($courseCode, $semesterCode, $sectionName);
        }

        if ($sectionCode !== '') {
            $parsed = self::parseSectionCode($sectionCode);
            if ($parsed) {
                return self::findByOffering(
                    $parsed['course_code'],
                    $parsed['semester_code'],
                    $parsed['section_name'],
                );
            }

            return self::findLegacy($sectionCode);
        }

        if ($sectionId !== '') {
            return self::findLegacy($sectionId);
        }

        return null;
    }

    public static function parseSectionCode(string $sectionCode): ?array
    {
        $sectionCode = trim($sectionCode);
        if ($sectionCode === '') {
            return null;
        }

        $parts = array_values(array_filter(explode('_', $sectionCode), fn ($p) => $p !== ''));
        if (count($parts) < 3) {
            return null;
        }

        $sectionName = array_pop($parts);
        $semesterCode = array_pop($parts);
        $courseCode = implode('_', $parts);

        return [
            'course_code' => $courseCode,
            'semester_code' => $semesterCode,
            'section_name' => $sectionName,
        ];
    }

    public static function buildSectionCode(Section $section): string
    {
        $section->loadMissing(['courseOffering.course', 'courseOffering.semester']);

        $courseCode = $section->courseOffering?->course?->course_code;
        $semesterCode = $section->courseOffering?->semester?->code;

        if ($courseCode && $semesterCode) {
            return sprintf('%s_%s_%s', $courseCode, $semesterCode, $section->section_name);
        }

        return (string) $section->section_name;
    }

    public static function findByOffering(string $courseCode, string $semesterCode, string $sectionName): ?Section
    {
        $course = Course::where('course_code', $courseCode)
            ->orWhereRaw('LOWER(course_code) = ?', [strtolower($courseCode)])
            ->first();

        if (! $course) {
            return null;
        }

        $semester = Semester::where('code', $semesterCode)
            ->orWhereRaw('LOWER(code) = ?', [strtolower($semesterCode)])
            ->first();

        if (! $semester) {
            return null;
        }

        $courseOffering = CourseOffering::where('course_id', $course->id)
            ->where('semester_id', $semester->id)
            ->first();

        if (! $courseOffering) {
            return null;
        }

        $candidates = array_unique(array_filter([
            $sectionName,
            self::withSectionPrefix($sectionName),
            self::withoutSectionPrefix($sectionName),
        ]));

        return Section::where('course_offering_id', $courseOffering->id)
            ->where(function ($query) use ($candidates) {
                foreach ($candidates as $candidate) {
                    $query->orWhere('section_name', $candidate)
                        ->orWhereRaw('LOWER(section_name) = ?', [strtolower($candidate)]);
                }
            })
            ->first();
    }

    public static function findLegacy(string $identifier): ?Section
    {
        if (is_numeric($identifier)) {
            $section = Section::find((int) $identifier);
            if ($section) {
                return $section;
            }
        }

        return Section::where('section_name', $identifier)
            ->orWhereRaw('LOWER(section_name) = ?', [strtolower($identifier)])
            ->first();
    }

    private static function withSectionPrefix(string $sectionName): string
    {
        if (preg_match('/^section\s+/i', $sectionName)) {
            return $sectionName;
        }

        return 'Section '.$sectionName;
    }

    private static function withoutSectionPrefix(string $sectionName): string
    {
        return preg_replace('/^section\s+/i', '', $sectionName) ?? $sectionName;
    }
}

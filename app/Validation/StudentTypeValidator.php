<?php

namespace App\Validation;

use App\Models\Student;
use App\Models\Section;
use App\Support\StudentScheduleRules;
use Illuminate\Support\Facades\Validator;

/**
 * Advanced validation for student type changes and bulk operations.
 * Provides comprehensive validation with detailed error messages.
 * 
 * @category Validation
 * @package App\Validation
 */
class StudentTypeValidator
{
    /**
     * Validate single student type change
     *
     * @param Student $student The student to validate
     * @param string $newType The new student type ('regular' or 'weekend')
     * @param array $context Additional context (section, enrollments, etc)
     * @return array Validation result with success status and errors
     */
    public static function validateStudentTypeChange(Student $student, string $newType, array $context = []): array
    {
        $errors = [];
        $warnings = [];

        // Validate the type value itself
        $typeValidation = self::validateTypeValue($newType);
        if (!$typeValidation['valid']) {
            return [
                'valid' => false,
                'errors' => $typeValidation['errors'],
                'warnings' => [],
                'student_id' => $student->student_id ?? null
            ];
        }

        // If no change needed, return success
        if ($student->student_type === $newType) {
            return [
                'valid' => true,
                'errors' => [],
                'warnings' => ['No change required - student already has type: ' . $newType],
                'student_id' => $student->student_id
            ];
        }

        // Check if student has existing schedules
        if ($student->schedules()->exists()) {
            $errors[] = "Cannot change student type: Student {$student->student_id} has existing schedules. Remove schedules first or use bulk migration tool.";
        }

        // Check for mixed enrollment if changing to regular
        if ($newType === 'regular') {
            $hasEnrollments = $student->enrollments()->exists();
            if ($hasEnrollments) {
                $warnings[] = "Changing to 'regular': Student will be scheduled only on weekday evenings (11:30-14:00). Verify this matches enrollment needs.";
            }
        }

        // Check for enrollment consistency if changing to weekend
        if ($newType === 'weekend') {
            $hasEnrollments = $student->enrollments()->exists();
            if ($hasEnrollments) {
                $warnings[] = "Changing to 'weekend': Student will be scheduled on weekday evenings + Saturday/Sunday. Ensure section supports this schedule.";
            }
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'warnings' => $warnings,
            'student_id' => $student->student_id
        ];
    }

    /**
     * Validate bulk student type import
     *
     * @param array $students Array of [student_id => type] pairs
     * @param bool $dryRun If true, only validate without making changes
     * @return array Detailed validation results
     */
    public static function validateBulkImport(array $students, bool $dryRun = true): array
    {
        $results = [
            'total' => count($students),
            'valid' => 0,
            'invalid' => 0,
            'warnings' => 0,
            'errors' => [],
            'warnings_list' => [],
            'successful' => [],
        ];

        foreach ($students as $studentId => $type) {
            $student = Student::where('student_id', $studentId)->first();

            if (!$student) {
                $results['invalid']++;
                $results['errors'][] = [
                    'student_id' => $studentId,
                    'error' => "Student with ID '{$studentId}' not found in system"
                ];
                continue;
            }

            $validation = self::validateStudentTypeChange($student, $type);

            if ($validation['valid']) {
                $results['valid']++;
                if (!empty($validation['warnings'])) {
                    $results['warnings']++;
                    $results['warnings_list'][] = [
                        'student_id' => $studentId,
                        'warnings' => $validation['warnings']
                    ];
                } else {
                    $results['successful'][] = $studentId;
                }
            } else {
                $results['invalid']++;
                $results['errors'][] = [
                    'student_id' => $studentId,
                    'errors' => $validation['errors']
                ];
            }
        }

        return $results;
    }

    /**
     * Validate section can be scheduled for a specific student type
     *
     * @param Section $section The section to validate
     * @param string $studentType The student type to check
     * @return array Validation result
     */
    public static function validateSectionForType(Section $section, string $studentType): array
    {
        $errors = [];
        $warnings = [];

        if (!in_array($studentType, ['regular', 'weekend'])) {
            return [
                'valid' => false,
                'errors' => ["Invalid student type: {$studentType}"],
                'warnings' => []
            ];
        }

        $students = $section->enrollments()->get()->pluck('student');
        $types = $students->pluck('student_type')->unique()->values()->toArray();

        if (count($types) > 1) {
            $errors[] = "Section {$section->code} has mixed student types: " . implode(', ', $types) . ". Cannot schedule sections with mixed types.";
        } elseif (count($types) === 1 && $types[0] !== $studentType) {
            $warnings[] = "Section students are type '{$types[0]}' but validating for type '{$studentType}'";
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'warnings' => $warnings,
            'section_id' => $section->id,
            'current_types' => $types
        ];
    }

    /**
     * Validate the student type value format
     *
     * @param string $type The type to validate
     * @return array Validation result
     */
    private static function validateTypeValue(string $type): array
    {
        $validator = Validator::make(
            ['student_type' => $type],
            [
                'student_type' => 'required|in:regular,weekend',
            ]
        );

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->get('student_type')
            ];
        }

        return [
            'valid' => true,
            'errors' => []
        ];
    }

    /**
     * Validate section schedule compatibility for student type
     *
     * @param Section $section The section to validate
     * @param string $studentType The student type ('regular' or 'weekend')
     * @return array Validation result with available timeslots
     */
    public static function validateScheduleCompatibility(Section $section, string $studentType): array
    {
        $validation = self::validateSectionForType($section, $studentType);

        if (!$validation['valid']) {
            return $validation;
        }

        $scheduleRules = new StudentScheduleRules();
        $availableTimeslots = 0;
        $totalTimeslots = 0;
        $timeslotsByDay = [];

        // Check all available timeslots
        $allTimeslots = \App\Models\Timeslot::all();
        foreach ($allTimeslots as $timeslot) {
            $totalTimeslots++;
            if ($scheduleRules->timeslotAllowedForType($studentType, $timeslot)) {
                $availableTimeslots++;
                $day = $timeslot->day;
                $timeslotsByDay[$day][] = $timeslot->start_time . ' - ' . $timeslot->end_time;
            }
        }

        return [
            'valid' => true,
            'errors' => [],
            'warnings' => $validation['warnings'] ?? [],
            'section_id' => $section->id,
            'student_type' => $studentType,
            'available_timeslots' => $availableTimeslots,
            'total_timeslots' => $totalTimeslots,
            'timeslots_by_day' => $timeslotsByDay
        ];
    }

    /**
     * Generate user-friendly validation summary
     *
     * @param array $validationResult The validation result array
     * @return string Formatted validation message
     */
    public static function formatValidationMessage(array $validationResult): string
    {
        $messages = [];

        if ($validationResult['valid']) {
            $messages[] = "✅ Validation passed";
        } else {
            $messages[] = "❌ Validation failed";
        }

        if (!empty($validationResult['errors'])) {
            $messages[] = "\nErrors:";
            foreach ($validationResult['errors'] as $error) {
                $messages[] = "  • " . (is_array($error) ? $error[0] : $error);
            }
        }

        if (!empty($validationResult['warnings'])) {
            $messages[] = "\nWarnings:";
            foreach ($validationResult['warnings'] as $warning) {
                $messages[] = "  ⚠ " . (is_array($warning) ? $warning[0] : $warning);
            }
        }

        return implode("\n", $messages);
    }
}

<?php

/**
 * Schedule generation rules (enforced by AutoSchedulerService).
 *
 * Academic section = student cohort (Student.academic_section, e.g. SE-3A).
 * Course section = class offering (Section model, linked via enrollments).
 *
 * All course sections for the same academic section use one home classroom.
 * At most two academic sections may share one physical room (when compatible).
 */
return [
    'max_teacher_hours_per_week' => 38,
    // Max student academic sections (cohorts) that may share one physical room.
    'room_max_sections' => 2,
    'room_combined_hours_limit' => 38,
    'enforce_student_conflicts' => true,
    'max_generation_attempts' => 5,
    // Teaching hours per assignment = timeslot end_time − start_time (exact, not rounded).
    'minutes_per_credit_hour' => 60,
    'days' => [
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5,
        'Saturday' => 6,
        'Sunday' => 7,
    ],
];

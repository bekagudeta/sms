<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\Student;
use Illuminate\Console\Command;

class BackfillCohortEnrollments extends Command
{
    protected $signature = 'students:backfill-cohort-enrollments {--dry-run}';

    protected $description = 'Diagnose cohort/schedule links and enroll students into the sections their academic_section cohort is already in.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->info('=== DIAGNOSIS ===');
        $this->line('Total enrollments: '.Enrollment::count());
        $this->line('Total schedules:   '.Schedule::count());

        // Which sections actually have schedules, and who is enrolled in them.
        $scheduledSectionIds = Schedule::query()->pluck('section_id')->unique()->values();
        $this->line('Sections that have schedules: ['.$scheduledSectionIds->implode(', ').']');

        $this->newLine();
        $this->info('Enrollment count per scheduled section (this is the cohort link):');
        foreach ($scheduledSectionIds as $sid) {
            $enr = Enrollment::where('section_id', $sid)->with('student')->get();
            $cohorts = $enr->map(fn ($e) => $e->student?->academic_section)->filter()->unique()->values();
            $this->line("  section #{$sid}: enrolled={$enr->count()} cohorts=[".$cohorts->implode(', ').']');
        }

        // Build cohort -> sectionIds map from existing enrollments.
        $cohortSections = [];
        foreach (Enrollment::with('student')->get() as $e) {
            $cohort = trim((string) ($e->student?->academic_section ?? ''));
            if ($cohort === '') {
                continue;
            }
            $cohortSections[strtolower($cohort)][$e->section_id] = $e->section_id;
        }

        $this->newLine();
        $this->info('Cohorts that have at least one enrollment: '.count($cohortSections));
        foreach ($cohortSections as $c => $ids) {
            $this->line("  {$c}: sections=[".implode(', ', $ids).']');
        }

        $this->newLine();
        $this->info('=== BACKFILL ==='.($dry ? ' (dry-run, no writes)' : ''));

        $created = 0;
        $studentsTouched = 0;

        foreach (Student::all() as $student) {
            if ($student->enrollments()->exists()) {
                continue; // already linked
            }

            $cohort = strtolower(trim((string) $student->academic_section));
            if ($cohort === '' || empty($cohortSections[$cohort])) {
                $this->line("  - #{$student->id} {$student->student_id} ({$student->academic_section}): no cohort sections to copy");
                continue;
            }

            $sectionIds = $cohortSections[$cohort];
            $studentsTouched++;
            $this->line("  + #{$student->id} {$student->student_id} ({$student->academic_section}): enrolling into [".implode(', ', $sectionIds).']');

            if ($dry) {
                continue;
            }

            foreach ($sectionIds as $sectionId) {
                $enrollment = Enrollment::firstOrCreate(
                    ['student_id' => $student->id, 'section_id' => $sectionId],
                    ['student_code' => $student->student_id, 'enrolled_at' => now()],
                );
                if ($enrollment->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        $this->newLine();
        $this->info("Done. Students updated: {$studentsTouched}. Enrollments created: {$created}.");

        return self::SUCCESS;
    }
}

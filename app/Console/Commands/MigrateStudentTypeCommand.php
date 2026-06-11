<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Validation\StudentTypeValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Artisan Command: Migrate students between regular and weekend types
 * 
 * Usage:
 *   php artisan students:migrate-type --from=regular --to=weekend --dry-run
 *   php artisan students:migrate-type --section=SE-3A --to=weekend --force
 *   php artisan students:migrate-type --department=SE --to=weekend --filter="level=300"
 * 
 * @category Commands
 * @package App\Commands
 */
class MigrateStudentTypeCommand extends Command
{
    protected $signature = 'students:migrate-type
        {--from= : Current student type to migrate from}
        {--to=weekend : Target student type}
        {--section= : Migrate specific academic section}
        {--department= : Migrate specific department}
        {--filter= : Additional where clause (e.g., "level=300")}
        {--dry-run : Preview changes without executing}
        {--force : Skip confirmation prompts}';

    protected $description = 'Migrate students between regular and weekend types with validation';

    public function handle()
    {
        $this->line('=== Student Type Migration Tool ===\n');

        $from = $this->option('from');
        $to = $this->option('to');
        $section = $this->option('section');
        $department = $this->option('department');
        $filter = $this->option('filter');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        // Validate options
        if (!in_array($to, ['regular', 'weekend'])) {
            $this->error('Target type must be "regular" or "weekend"');
            return 1;
        }

        // Build query
        $query = Student::query();

        if ($from) {
            if (!in_array($from, ['regular', 'weekend'])) {
                $this->error('Source type must be "regular" or "weekend"');
                return 1;
            }
            $query->where('student_type', $from);
        }

        if ($section) {
            $query->where('academic_section', $section);
        }

        if ($department) {
            $query->where('department_id', $department);
        }

        if ($filter) {
            // Parse simple filter format: key=value
            preg_match('/(\w+)=(.+)/', $filter, $matches);
            if (count($matches) === 3) {
                $query->where($matches[1], $matches[2]);
            }
        }

        $students = $query->get();

        $this->info("Found {$students->count()} students to migrate");
        $this->line("Migration: " . ($from ? $from : 'any type') . " → {$to}");

        if ($section) {
            $this->line("Section: {$section}");
        }
        if ($department) {
            $this->line("Department: {$department}\n");
        }

        // Validate before migrating
        $this->line('Validating students...');

        $validationResults = [
            'valid' => 0,
            'invalid' => 0,
            'errors' => [],
            'warnings' => [],
            'students_to_migrate' => [],
        ];

        foreach ($students as $student) {
            $validation = StudentTypeValidator::validateStudentTypeChange($student, $to);

            if ($validation['valid']) {
                $validationResults['valid']++;
                $validationResults['students_to_migrate'][] = $student;
                if (!empty($validation['warnings'])) {
                    $validationResults['warnings'][] = [
                        'student_id' => $student->student_id,
                        'warning' => $validation['warnings'][0]
                    ];
                }
            } else {
                $validationResults['invalid']++;
                $validationResults['errors'][] = [
                    'student_id' => $student->student_id,
                    'error' => $validation['errors'][0] ?? 'Validation failed'
                ];
            }
        }

        // Display validation summary
        $this->newLine();
        $this->table(
            ['Status', 'Count'],
            [
                ['Valid', $validationResults['valid']],
                ['Invalid', $validationResults['invalid']],
                ['Warnings', count($validationResults['warnings'])],
            ]
        );

        if ($validationResults['invalid'] > 0) {
            $this->warn('\nErrors found:');
            foreach ($validationResults['errors'] as $error) {
                $this->line("  • {$error['student_id']}: {$error['error']}");
            }
        }

        if ($validationResults['valid'] === 0) {
            $this->error('No valid students to migrate');
            return 1;
        }

        // Dry run
        if ($dryRun) {
            $this->info('\n[DRY RUN] Would migrate the following students:');
            foreach (array_slice($validationResults['students_to_migrate'], 0, 10) as $student) {
                $this->line("  • {$student->student_id}: {$student->first_name} {$student->last_name} ({$student->student_type} → {$to})");
            }
            if ($validationResults['valid'] > 10) {
                $this->line("  ... and " . ($validationResults['valid'] - 10) . " more");
            }
            $this->info('\nUse --force to execute migration');
            return 0;
        }

        // Confirm before executing
        if (!$force) {
            if (!$this->confirm("Migrate {$validationResults['valid']} students?")) {
                $this->line('Migration cancelled');
                return 0;
            }
        }

        // Execute migration
        $this->line('Executing migration...');
        $bar = $this->output->createProgressBar($validationResults['valid']);

        DB::beginTransaction();

        try {
            $updated = 0;
            foreach ($validationResults['students_to_migrate'] as $student) {
                $oldType = $student->student_type;
                $student->update(['student_type' => $to]);

                // Log the change
                DB::table('audit_logs')->insert([
                    'user_id' => auth()->id() ?? 1,
                    'action' => 'updated',
                    'model' => 'Student',
                    'model_id' => $student->id,
                    'changes' => json_encode([
                        'student_type' => ['old' => $oldType, 'new' => $to]
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $updated++;
                $bar->advance();
            }

            DB::commit();
            $bar->finish();

            $this->newLine(2);
            $this->info("✓ Migration completed successfully!");
            $this->line("Updated: {$updated} students");
            $this->line("Failed: " . $validationResults['invalid']);

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\n✗ Migration failed: " . $e->getMessage());
            return 1;
        }
    }
}

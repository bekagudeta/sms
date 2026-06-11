<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add performance optimization indexes for student type scheduling
 * 
 * Indexes improve query performance for:
 * - Finding students by type
 * - Filtering schedules by student type
 * - Bulk operations
 * - Reporting and statistics
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Index on student_type for quick filtering
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'student_type')) {
                return; // Already added in previous migration
            }
            
            $table->index('student_type', 'idx_student_type');
        });

        // Composite index for common queries
        Schema::table('students', function (Blueprint $table) {
            $table->index(['student_type', 'academic_section'], 'idx_student_type_section');
        });

        // Index for department filtering with student type
        Schema::table('students', function (Blueprint $table) {
            $table->index(['department_id', 'student_type'], 'idx_dept_student_type');
        });

        // Index for schedules - quick lookup by section and type
        Schema::table('schedules', function (Blueprint $table) {
            if (Schema::hasColumn('schedules', 'section_id')) {
                $table->index('section_id', 'idx_schedule_section');
            }
        });

        // Index for timeslot lookups
        Schema::table('timeslots', function (Blueprint $table) {
            if (Schema::hasColumn('timeslots', 'day')) {
                $table->index('day', 'idx_timeslot_day');
            }
            if (Schema::hasColumn('timeslots', 'session')) {
                $table->index('session', 'idx_timeslot_session');
            }
        });

        // Create audit log table for tracking changes
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action'); // created, updated, deleted
                $table->string('model'); // Student, Schedule, etc
                $table->unsignedBigInteger('model_id');
                $table->json('changes')->nullable(); // Old and new values
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                $table->index(['model', 'model_id']);
                $table->index('user_id');
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_student_type');
            $table->dropIndexIfExists('idx_student_type_section');
            $table->dropIndexIfExists('idx_dept_student_type');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_schedule_section');
        });

        Schema::table('timeslots', function (Blueprint $table) {
            $table->dropIndexIfExists('idx_timeslot_day');
            $table->dropIndexIfExists('idx_timeslot_session');
        });

        if (Schema::hasTable('audit_logs')) {
            Schema::dropIfExists('audit_logs');
        }
    }
};

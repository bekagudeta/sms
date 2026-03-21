<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'student_count')) {
                $table->integer('student_count')->default(30)->after('hours_per_week');
            }
            if (!Schema::hasColumn('courses', 'required_room_type')) {
                $table->string('required_room_type')->default('lecture')->after('student_count');
            }
        });

        Schema::table('teachers', function (Blueprint $table) {
            if (!Schema::hasColumn('teachers', 'specialization')) {
                $table->string('specialization')->nullable()->after('qualification');
            }
        });

        Schema::table('schedules', function (Blueprint $table) {
            // Add student group tracking for conflict detection
            if (!Schema::hasColumn('schedules', 'student_group')) {
                $table->string('student_group')->nullable()->after('section');
                
                // Add index for faster conflict detection
                $table->index(['student_group', 'timeslot_id', 'semester_id'], 'idx_student_group_conflict');
            }
        });
    }

    public function down()
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['student_count', 'required_room_type']);
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('specialization');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('student_group');
        });
    }
};

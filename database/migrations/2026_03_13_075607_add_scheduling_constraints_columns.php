<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'student_count')) {
                $table->integer('student_count')->nullable()->after('level');
            }
            if (!Schema::hasColumn('courses', 'required_room_type')) {
                $table->string('required_room_type')->nullable()->after('student_count');
            }
        });

        Schema::table('teachers', function (Blueprint $table) {
            if (!Schema::hasColumn('teachers', 'specialization')) {
                $table->string('specialization')->nullable()->after('qualification');
            }
        });

        Schema::table('schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('schedules', 'student_group')) {
                $table->string('student_group')->nullable()->after('section');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
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

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
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('day')->nullable()->after('semester_id');
            $table->time('start_time')->nullable()->after('day');
            $table->time('end_time')->nullable()->after('start_time');
            $table->string('student_group')->nullable()->after('section');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['day', 'start_time', 'end_time', 'student_group']);
        });
    }
};

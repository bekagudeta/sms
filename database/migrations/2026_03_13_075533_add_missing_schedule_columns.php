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
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('schedules', 'day')) {
                $table->string('day')->nullable()->after('semester_id');
            }
            if (!Schema::hasColumn('schedules', 'start_time')) {
                $table->time('start_time')->nullable()->after('day');
            }
            if (!Schema::hasColumn('schedules', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['day', 'start_time', 'end_time']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timeslots', function (Blueprint $table) {
            $table->string('student_type', 20)
                ->default('regular')
                ->after('end_time');
        });

        DB::statement("UPDATE timeslots SET student_type = CASE
            WHEN slot_code LIKE 'WKE_%' THEN 'weekend'
            ELSE 'regular'
            END
        ");
    }

    public function down(): void
    {
        Schema::table('timeslots', function (Blueprint $table) {
            $table->dropColumn('student_type');
        });
    }
};

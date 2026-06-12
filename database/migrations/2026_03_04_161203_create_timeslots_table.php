<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('timeslots', function (Blueprint $table) {
            $table->id();
            $table->string('day_of_week'); // Monday, Tuesday, etc.
            $table->time('start_time');
            $table->time('end_time');
            $table->string('student_type', 20)->default('regular');
            $table->string('slot_code')->unique(); // e.g., MON_10_12
            $table->timestamps();
        });
        DB::statement("UPDATE timeslots SET student_type = CASE
            WHEN slot_code LIKE 'WKE_%' THEN 'weekend'
            ELSE 'regular'
            END
        ");
    }

    public function down()
    {
        Schema::dropIfExists('timeslots');
    }
};
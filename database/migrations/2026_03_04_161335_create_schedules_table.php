<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->foreignId('timeslot_id')->constrained()->onDelete('cascade');
            $table->foreignId('semester_id')->constrained()->onDelete('cascade');
            $table->string('section')->nullable();
            $table->integer('max_students');
            $table->enum('status', ['scheduled', 'cancelled', 'completed'])->default('scheduled');
            $table->timestamps();
            
            // Ensure no conflicts
            $table->unique(['room_id', 'timeslot_id', 'semester_id'], 'unique_room_timeslot');
            $table->unique(['teacher_id', 'timeslot_id', 'semester_id'], 'unique_teacher_timeslot');
        });
    }

    public function down()
    {
        Schema::dropIfExists('schedules');
    }
};
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
            $table->foreignId('section_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_id')->constrained()->onDelete('cascade');
            $table->foreignId('timeslot_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // Core constraint: no room can be used at the same time
            $table->unique(['room_id', 'timeslot_id'], 'unique_room_timeslot');
        });
    }

    public function down()
    {
        Schema::dropIfExists('schedules');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_code')->unique();
            $table->string('building');
            $table->integer('floor');
            $table->integer('capacity');
            $table->enum('type', ['lecture', 'lab', 'seminar', 'conference']);
            $table->boolean('has_projector')->default(false);
            $table->boolean('has_computers')->default(false);
            $table->integer('computer_count')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('rooms');
    }
};
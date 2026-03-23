<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('semester_id')->constrained()->onDelete('cascade');
            $table->integer('expected_students');
            $table->timestamps();
            
            // Each course can only be offered once per semester
            $table->unique(['course_id', 'semester_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('course_offerings');
    }
};

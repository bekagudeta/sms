<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_offering_id')->constrained()->onDelete('cascade');
            $table->string('section_name'); // A, B, C, etc.
            $table->integer('capacity');
            $table->timestamps();
            
            // Section names must be unique within a course offering
            $table->unique(['course_offering_id', 'section_name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sections');
    }
};

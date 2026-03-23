<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('section_teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // Prevent duplicate teacher assignments to same section
            $table->unique(['section_id', 'teacher_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('section_teachers');
    }
};

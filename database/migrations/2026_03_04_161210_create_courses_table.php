<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('course_code')->unique();
            $table->string('course_name');
            $table->text('description')->nullable();
            $table->integer('credits');
            $table->integer('hours_per_week');
            $table->foreignId('department_id')->constrained();
            $table->foreignId('semester_id')->constrained();
            $table->foreignId('teacher_id')->nullable()->constrained();
            $table->enum('level', ['undergraduate', 'graduate', 'diploma']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('courses');
    }
};
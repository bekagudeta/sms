<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->string('student_code', 50)->nullable();
            $table->foreignId('section_id')->constrained()->onDelete('cascade');
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamps();
            
            // Prevent duplicate enrollments
            $table->unique(['student_id', 'section_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('enrollments');
    }
};

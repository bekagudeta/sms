<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('courses', function (Blueprint $table) {
            // Remove fields that don't belong in course definition
            $table->dropForeign(['semester_id']);
            $table->dropForeign(['teacher_id']);
            $table->dropColumn(['semester_id', 'teacher_id']);
        });
    }

    public function down()
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->constrained();
            $table->foreignId('teacher_id')->nullable()->constrained();
        });
    }
};

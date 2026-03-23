<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('courses', function (Blueprint $table) {
            // Already removed semester_id and teacher_id from courses table
        });
    }

    public function down()
    {
        Schema::table('courses', function (Blueprint $table) {
            // Do not re-add semester_id and teacher_id
        });
    }
};

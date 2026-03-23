<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('students', function (Blueprint $table) {
            // Remove semester field - weak design, enrollment info is in enrollments table
            $table->dropColumn('semester');
        });
    }

    public function down()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->integer('semester')->after('department_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('students', 'section')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $table->string('academic_section')->default('')->after('level');
        });

        DB::table('students')->update([
            'academic_section' => DB::raw('section'),
        ]);

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('section');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('students', 'academic_section')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $table->string('section')->default('')->after('level');
        });

        DB::table('students')->update([
            'section' => DB::raw('academic_section'),
        ]);

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('academic_section');
        });
    }
};

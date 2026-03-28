<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'grade')) {
                $table->integer('grade')->nullable()->after('section');
            }

            if (!Schema::hasColumn('students', 'status')) {
                $table->enum('status', ['active', 'inactive', 'pending', 'graduated', 'suspended'])
                    ->nullable()
                    ->after('grade')
                    ->default('active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('students', 'grade')) {
                $table->dropColumn('grade');
            }
        });
    }
};
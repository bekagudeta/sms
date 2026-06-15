<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SECURITY: Remove the plain_password column. Storing user passwords in plaintext
 * is a critical vulnerability for a multi-user university system (a single DB or
 * backup leak would expose every real password). Temporary credentials are now
 * generated in-memory at import/export time and only the hash is ever persisted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'plain_password')) {
            // Best-effort scrub of any existing plaintext before the column is dropped.
            DB::table('users')->update(['plain_password' => null]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('plain_password');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'plain_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('plain_password')->nullable();
            });
        }
    }
};

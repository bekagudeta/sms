<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove security risk fields
            $table->dropColumn(['plain_password', 'must_change_password']);
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('plain_password')->nullable();
            $table->boolean('must_change_password')->default(false);
        });
    }
};

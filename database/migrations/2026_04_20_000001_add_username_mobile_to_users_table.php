<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // username: unique alias for login (optional at signup — defaults to name)
            $table->string('username')->nullable()->unique()->after('name');
            // mobile: E.164 format, optional
            $table->string('mobile', 20)->nullable()->unique()->after('email');
            // make email nullable so India users without email can register
            $table->string('email')->nullable()->change();
        });

        // Remove the unique constraint on email, re-add as partial (non-null only)
        // We enforce unique email in PHP when email is provided
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'mobile']);
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
        });
    }
};

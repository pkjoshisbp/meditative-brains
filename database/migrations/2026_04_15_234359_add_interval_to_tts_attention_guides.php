<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tts_attention_guides', function (Blueprint $table) {
            // interval in milliseconds (e.g. 60000 = 60 seconds); default 60s
            $table->unsignedInteger('interval_ms')->default(60000)->after('speed');
            $table->boolean('is_active')->default(true)->after('interval_ms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tts_attention_guides', function (Blueprint $table) {
            $table->dropColumn(['interval_ms', 'is_active']);
        });
    }
};

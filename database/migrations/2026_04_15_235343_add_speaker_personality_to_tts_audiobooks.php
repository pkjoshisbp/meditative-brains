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
        Schema::table('tts_audiobooks', function (Blueprint $table) {
            $table->string('speaker_personality', 80)->nullable()->after('speaker_style');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tts_audiobooks', function (Blueprint $table) {
            $table->dropColumn('speaker_personality');
        });
    }
};

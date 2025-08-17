<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tts_audio_products', function (Blueprint $table) {
            if (!Schema::hasColumn('tts_audio_products', 'group_key')) {
                $table->string('group_key', 160)->nullable()->after('slug')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tts_audio_products', function (Blueprint $table) {
            if (Schema::hasColumn('tts_audio_products', 'group_key')) {
                $table->dropColumn('group_key');
            }
        });
    }
};

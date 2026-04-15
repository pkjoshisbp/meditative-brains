<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tts_audio_products') || !Schema::hasColumn('tts_audio_products', 'preview_audio_url')) {
            return;
        }

        DB::statement('ALTER TABLE tts_audio_products MODIFY preview_audio_url TEXT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('tts_audio_products') || !Schema::hasColumn('tts_audio_products', 'preview_audio_url')) {
            return;
        }

        DB::statement('ALTER TABLE tts_audio_products MODIFY preview_audio_url VARCHAR(255) NULL');
    }
};
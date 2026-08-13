<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tts_audiobooks', function (Blueprint $table) {
            if (!Schema::hasColumn('tts_audiobooks', 'has_background_music')) {
                $table->boolean('has_background_music')->default(false)->after('preview_audio_url');
            }

            if (!Schema::hasColumn('tts_audiobooks', 'background_music_track')) {
                $table->string('background_music_track', 150)->nullable()->after('has_background_music');
            }

            if (!Schema::hasColumn('tts_audiobooks', 'background_music_volume')) {
                $table->float('background_music_volume')->default(0.25)->after('background_music_track');
            }

            if (!Schema::hasColumn('tts_audiobooks', 'tts_audio_volume')) {
                $table->float('tts_audio_volume')->default(1.0)->after('background_music_volume');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tts_audiobooks', function (Blueprint $table) {
            $dropColumns = [];

            foreach ([
                'has_background_music',
                'background_music_track',
                'background_music_volume',
                'tts_audio_volume',
            ] as $column) {
                if (Schema::hasColumn('tts_audiobooks', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};

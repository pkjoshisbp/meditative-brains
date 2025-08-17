<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tts_audio_products', function (Blueprint $table) {
            if (!Schema::hasColumn('tts_audio_products', 'bg_music_volume')) {
                $table->float('bg_music_volume')->default(0.30)->after('is_active');
            }
            if (!Schema::hasColumn('tts_audio_products', 'message_repeat_count')) {
                $table->integer('message_repeat_count')->default(2)->after('bg_music_volume');
            }
            if (!Schema::hasColumn('tts_audio_products', 'repeat_interval')) {
                $table->float('repeat_interval')->default(2.0)->after('message_repeat_count');
            }
            if (!Schema::hasColumn('tts_audio_products', 'message_interval')) {
                $table->float('message_interval')->default(10.0)->after('repeat_interval');
            }
            if (!Schema::hasColumn('tts_audio_products', 'fade_in_duration')) {
                $table->float('fade_in_duration')->default(0.5)->after('message_interval');
            }
            if (!Schema::hasColumn('tts_audio_products', 'fade_out_duration')) {
                $table->float('fade_out_duration')->default(0.5)->after('fade_in_duration');
            }
            if (!Schema::hasColumn('tts_audio_products', 'enable_silence_padding')) {
                $table->boolean('enable_silence_padding')->default(true)->after('fade_out_duration');
            }
            if (!Schema::hasColumn('tts_audio_products', 'silence_start')) {
                $table->float('silence_start')->default(1.0)->after('enable_silence_padding');
            }
            if (!Schema::hasColumn('tts_audio_products', 'silence_end')) {
                $table->float('silence_end')->default(1.0)->after('silence_start');
            }
            if (!Schema::hasColumn('tts_audio_products', 'has_background_music')) {
                $table->boolean('has_background_music')->default(false)->after('silence_end');
            }
            if (!Schema::hasColumn('tts_audio_products', 'background_music_type')) {
                $table->string('background_music_type', 100)->nullable()->after('has_background_music');
            }
            if (!Schema::hasColumn('tts_audio_products', 'background_music_track')) {
                $table->string('background_music_track', 150)->nullable()->after('background_music_type');
            }
            if (!Schema::hasColumn('tts_audio_products', 'audio_urls')) {
                $table->longText('audio_urls')->nullable()->after('background_music_track');
            }
            if (!Schema::hasColumn('tts_audio_products', 'preview_audio_url')) {
                $table->string('preview_audio_url', 500)->nullable()->after('audio_urls');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tts_audio_products', function (Blueprint $table) {
            if (Schema::hasColumn('tts_audio_products', 'background_music_track')) {
                $table->dropColumn('background_music_track');
            }
        });
    }
};

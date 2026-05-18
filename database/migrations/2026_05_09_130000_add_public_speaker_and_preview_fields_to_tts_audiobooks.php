<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tts_audiobooks', function (Blueprint $table) {
            if (!Schema::hasColumn('tts_audiobooks', 'public_speaker_name')) {
                $table->string('public_speaker_name', 120)->nullable()->after('speaker');
            }

            if (!Schema::hasColumn('tts_audiobooks', 'preview_chapter_number')) {
                $table->unsignedSmallInteger('preview_chapter_number')->nullable()->after('prosody_volume');
            }

            if (!Schema::hasColumn('tts_audiobooks', 'preview_audio_path')) {
                $table->string('preview_audio_path', 700)->nullable()->after('preview_chapter_number');
            }

            if (!Schema::hasColumn('tts_audiobooks', 'preview_audio_url')) {
                $table->string('preview_audio_url', 700)->nullable()->after('preview_audio_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tts_audiobooks', function (Blueprint $table) {
            $dropColumns = [];

            if (Schema::hasColumn('tts_audiobooks', 'public_speaker_name')) {
                $dropColumns[] = 'public_speaker_name';
            }

            if (Schema::hasColumn('tts_audiobooks', 'preview_chapter_number')) {
                $dropColumns[] = 'preview_chapter_number';
            }

            if (Schema::hasColumn('tts_audiobooks', 'preview_audio_path')) {
                $dropColumns[] = 'preview_audio_path';
            }

            if (Schema::hasColumn('tts_audiobooks', 'preview_audio_url')) {
                $dropColumns[] = 'preview_audio_url';
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
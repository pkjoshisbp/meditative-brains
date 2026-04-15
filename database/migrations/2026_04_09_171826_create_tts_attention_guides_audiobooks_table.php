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
        // ── Attention guides ─────────────────────────────────────────────────────
        if (!Schema::hasTable('tts_attention_guides')) {
            Schema::create('tts_attention_guides', function (Blueprint $table) {
                $table->id();
                $table->string('mongo_id', 30)->nullable()->index();
                $table->text('text');
                $table->string('language', 20)->default('en-US');
                $table->string('speaker', 100)->default('en-US-AriaNeural');
                $table->string('engine', 20)->default('azure');
                $table->string('speaker_style', 80)->nullable();
                $table->string('category', 100)->default('attention-guide');
                $table->string('speed', 20)->default('medium');
                $table->string('audio_path', 500)->nullable();
                $table->string('audio_url', 700)->nullable();
                $table->timestamps();
            });
        }

        // ── Audiobooks ───────────────────────────────────────────────────────────
        if (!Schema::hasTable('tts_audiobooks')) {
            Schema::create('tts_audiobooks', function (Blueprint $table) {
                $table->id();
                $table->string('mongo_id', 30)->nullable()->index();
                $table->string('book_title', 255)->unique();
                $table->string('book_author', 255)->default('');
                $table->string('language', 20)->default('en-US');
                $table->string('speaker', 100)->default('en-US-AriaNeural');
                $table->string('engine', 20)->default('azure');
                $table->string('speaker_style', 80)->nullable();
                $table->string('expression_style', 80)->nullable();
                $table->string('prosody_rate', 20)->default('medium');
                $table->string('prosody_pitch', 20)->default('medium');
                $table->string('prosody_volume', 20)->default('medium');
                $table->timestamps();
            });
        }

        // ── Audiobook chapters ───────────────────────────────────────────────────
        if (!Schema::hasTable('tts_audiobook_chapters')) {
            Schema::create('tts_audiobook_chapters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('audiobook_id')->index();
                $table->unsignedSmallInteger('chapter_number');
                $table->string('title', 500)->default('');
                $table->longText('plain_content')->nullable();
                $table->longText('ssml_content')->nullable();
                $table->string('audio_path', 700)->nullable();
                $table->string('audio_url', 700)->nullable();
                $table->enum('status', ['pending', 'generating', 'done', 'error'])->default('pending');
                $table->timestamps();

                $table->foreign('audiobook_id')
                      ->references('id')->on('tts_audiobooks')->cascadeOnDelete();
                $table->unique(['audiobook_id', 'chapter_number']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tts_audiobook_chapters');
        Schema::dropIfExists('tts_audiobooks');
        Schema::dropIfExists('tts_attention_guides');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Languages ────────────────────────────────────────────────────────────
        if (!Schema::hasTable('tts_languages')) {
            Schema::create('tts_languages', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name', 100);
                $table->string('local_name', 100)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // ── Source categories (migrated from Mongo Category) ─────────────────────
        if (!Schema::hasTable('tts_source_categories')) {
            Schema::create('tts_source_categories', function (Blueprint $table) {
                $table->id();
                $table->string('mongo_id', 30)->nullable()->index();   // original Mongo _id
                $table->string('category', 255)->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->timestamps();

                $table->unique(['category', 'user_id']);
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        // ── Motivation messages (migrated from Mongo MotivationMessage) ──────────
        if (!Schema::hasTable('tts_motivation_messages')) {
            Schema::create('tts_motivation_messages', function (Blueprint $table) {
                $table->id();
                $table->string('mongo_id', 30)->nullable()->index();
                $table->unsignedBigInteger('source_category_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->json('messages');              // array of text sentences
                $table->json('ssml_messages')->nullable();
                $table->json('ssml')->nullable();
                $table->string('engine', 20)->default('azure');
                $table->string('language', 20)->default('en-US');
                $table->string('speaker', 100)->default('en-US-AriaNeural');
                $table->string('speaker_style', 80)->nullable();
                $table->string('speaker_personality', 80)->nullable();
                $table->string('prosody_pitch', 20)->default('medium');
                $table->string('prosody_rate', 20)->default('medium');
                $table->string('prosody_volume', 20)->default('medium');
                $table->json('audio_paths')->nullable();
                $table->json('audio_urls')->nullable();
                $table->boolean('editable')->default(true);
                $table->timestamps();

                $table->foreign('source_category_id')
                      ->references('id')->on('tts_source_categories')->cascadeOnDelete();
                $table->foreign('user_id')
                      ->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tts_motivation_messages');
        Schema::dropIfExists('tts_source_categories');
        Schema::dropIfExists('tts_languages');
    }
};

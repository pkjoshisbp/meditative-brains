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
        Schema::create('tts_audio_products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('category', 100);
            $table->string('language', 10)->default('en');
            $table->decimal('price', 8, 2);
            $table->integer('preview_duration')->default(30); // seconds
            $table->string('background_music_url', 500)->nullable();
            $table->string('cover_image_url', 500)->nullable();
            $table->json('sample_messages')->nullable(); // 3-5 sample messages for preview
            $table->integer('total_messages_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['category', 'is_active']);
            $table->index(['language', 'is_active']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tts_audio_products');
    }
};

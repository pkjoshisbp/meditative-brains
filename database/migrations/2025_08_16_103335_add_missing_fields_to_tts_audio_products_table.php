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
        Schema::table('tts_audio_products', function (Blueprint $table) {
            // Add missing meta fields
            $table->string('meta_title')->nullable()->after('tags');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->string('meta_keywords')->nullable()->after('meta_description');
            
            // Add cover image path field
            $table->string('cover_image_path')->nullable()->after('cover_image_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tts_audio_products', function (Blueprint $table) {
            $table->dropColumn(['meta_title', 'meta_description', 'meta_keywords', 'cover_image_path']);
        });
    }
};

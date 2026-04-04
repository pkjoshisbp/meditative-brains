<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks multiple versions (language/accent) of the same product.
 * e.g. Supreme Confidence in Hindi, Indian accent, US accent.
 * For subscriptions: each version counts as a separate product slot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');   // parent product
            $table->string('version_label', 100);        // 'Hindi Audio', 'US Accent', 'Indian Accent', 'Hindi PDF'
            $table->string('language', 10)->default('en');
            $table->string('accent', 50)->nullable();    // 'indian', 'us', 'uk', 'hindi'
            $table->string('product_type', 30)->default('audio'); // 'audio', 'ebook_pdf', 'ebook_bundle'
            $table->decimal('price', 8, 2)->nullable();  // override price for this version (null = same as parent)
            $table->decimal('inr_price', 10, 2)->nullable();
            $table->string('audio_url', 500)->nullable();
            $table->string('pdf_file_path', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('tts_audio_products')->onDelete('cascade');
            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_versions');
    }
};

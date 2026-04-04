<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tts_audio_products', function (Blueprint $table) {
            // Product type: 'audio', 'ebook_pdf', 'ebook_bundle'
            $table->string('product_type', 30)->default('audio')->after('is_active');

            // INR pricing ($1 = ₹100 base)
            $table->decimal('inr_price', 10, 2)->nullable()->after('sale_price');
            $table->decimal('inr_sale_price', 10, 2)->nullable()->after('inr_price');

            // Book-specific pricing tiers
            $table->decimal('pdf_price', 8, 2)->nullable()->after('inr_sale_price');       // PDF only
            $table->decimal('bundle_price', 8, 2)->nullable()->after('pdf_price');          // PDF + audio
            $table->decimal('audio_only_price', 8, 2)->nullable()->after('bundle_price');   // Audio only (app)
            $table->decimal('pdf_price_inr', 10, 2)->nullable()->after('audio_only_price');
            $table->decimal('bundle_price_inr', 10, 2)->nullable()->after('pdf_price_inr');

            // Book files
            $table->string('pdf_file_path', 500)->nullable()->after('bundle_price_inr');
            $table->string('pdf_file_url', 500)->nullable()->after('pdf_file_path');

            // Version / language info
            $table->string('accent', 50)->nullable()->after('language'); // 'indian', 'us', 'uk', 'hindi'
            $table->unsignedBigInteger('parent_product_id')->nullable()->after('group_key');

            $table->index('product_type');
            $table->index('parent_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('tts_audio_products', function (Blueprint $table) {
            $table->dropColumn([
                'product_type', 'inr_price', 'inr_sale_price',
                'pdf_price', 'bundle_price', 'audio_only_price',
                'pdf_price_inr', 'bundle_price_inr',
                'pdf_file_path', 'pdf_file_url',
                'accent', 'parent_product_id'
            ]);
        });
    }
};

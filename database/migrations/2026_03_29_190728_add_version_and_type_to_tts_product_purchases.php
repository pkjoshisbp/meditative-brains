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
        Schema::table('tts_product_purchases', function (Blueprint $table) {
            $table->unsignedBigInteger('version_id')->nullable()->after('tts_audio_product_id');
            $table->string('product_type', 30)->default('audio')->after('version_id');
            $table->string('transaction_id', 200)->nullable()->after('product_type');
            $table->string('payment_method', 50)->nullable()->after('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('tts_product_purchases', function (Blueprint $table) {
            $table->dropColumn(['version_id', 'product_type', 'transaction_id', 'payment_method']);
        });
    }
};

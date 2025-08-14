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
        Schema::create('music_access_controls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('content_type'); // 'music', 'tts_category', 'single_product'
            $table->string('content_identifier'); // product_id, category_name, or 'all_music'
            $table->string('access_type'); // 'single_purchase', 'subscription', 'trial'
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable(); // null for lifetime access
            $table->boolean('is_active')->default(true);
            $table->string('purchase_reference')->nullable(); // order_id or subscription_id
            $table->json('metadata')->nullable(); // additional info about the access
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'content_type', 'is_active']);
            $table->index(['content_type', 'content_identifier']);
            $table->index(['access_type', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_access_controls');
    }
};

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
        Schema::create('tts_category_access', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('category_name'); // 'Self Confidence', 'Positive Attitude', etc.
            $table->string('access_type'); // 'single_purchase', 'subscription'
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable(); // null for lifetime access
            $table->boolean('is_active')->default(true);
            $table->string('purchase_reference')->nullable(); // order_id or subscription_id
            $table->decimal('price_paid', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'category_name', 'is_active']);
            $table->index(['category_name', 'access_type']);
            $table->unique(['user_id', 'category_name']); // One access record per user per category
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tts_category_access');
    }
};

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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 'Music Library Monthly', 'TTS Complete', 'Premium All Access'
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('billing_cycle'); // 'monthly', 'yearly', 'lifetime'
            $table->json('features')->nullable(); // what's included
            $table->json('access_rules')->nullable(); // specific access permissions
            $table->boolean('includes_music_library')->default(false);
            $table->boolean('includes_all_tts_categories')->default(false);
            $table->json('included_tts_categories')->nullable(); // specific categories if not all
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('trial_days')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('billing_cycle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};

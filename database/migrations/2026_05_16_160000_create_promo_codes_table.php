<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 10, 2);
            $table->timestamp('valid_until')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('applies_to', 30)->default('subscriptions');
            $table->timestamps();

            $table->index(['is_active', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
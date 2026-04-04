<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // How many individual products subscriber can pick per month (null = unlimited)
            $table->integer('max_products')->nullable()->after('trial_days');
            // INR price
            $table->decimal('inr_price', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['max_products', 'inr_price']);
        });
    }
};

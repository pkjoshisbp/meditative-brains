<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('referral_code', 64)->unique();
            $table->string('status', 20)->default('pending');
            $table->decimal('commission_rate', 5, 2)->default(10.00);
            $table->string('payout_email')->nullable();
            $table->text('application_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_id', 120)->nullable();
            $table->string('landing_url', 2048)->nullable();
            $table->string('referrer_url', 2048)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_profile_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('status', 20)->default('paid');
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('affiliate_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_click_id')->nullable()->constrained('affiliate_clicks')->nullOnDelete();
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tts_product_purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('affiliate_payout_id')->nullable()->constrained()->nullOnDelete();
            $table->string('conversion_type', 40);
            $table->string('currency', 10)->default('USD');
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->string('status', 20)->default('approved');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('affiliate_profile_id')->nullable()->after('user_id')->constrained('affiliate_profiles')->nullOnDelete();
            $table->foreignId('affiliate_click_id')->nullable()->after('affiliate_profile_id')->constrained('affiliate_clicks')->nullOnDelete();
            $table->string('affiliate_referral_code', 64)->nullable()->after('affiliate_click_id');
            $table->decimal('affiliate_commission_rate', 5, 2)->nullable()->after('affiliate_referral_code');
            $table->decimal('affiliate_commission_amount', 10, 2)->nullable()->after('affiliate_commission_rate');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('affiliate_profile_id')->nullable()->after('user_id')->constrained('affiliate_profiles')->nullOnDelete();
            $table->foreignId('affiliate_click_id')->nullable()->after('affiliate_profile_id')->constrained('affiliate_clicks')->nullOnDelete();
            $table->string('affiliate_referral_code', 64)->nullable()->after('affiliate_click_id');
            $table->decimal('affiliate_commission_rate', 5, 2)->nullable()->after('affiliate_referral_code');
            $table->decimal('affiliate_commission_amount', 10, 2)->nullable()->after('affiliate_commission_rate');
        });

        Schema::table('tts_product_purchases', function (Blueprint $table) {
            $table->foreignId('affiliate_profile_id')->nullable()->after('user_id')->constrained('affiliate_profiles')->nullOnDelete();
            $table->foreignId('affiliate_click_id')->nullable()->after('affiliate_profile_id')->constrained('affiliate_clicks')->nullOnDelete();
            $table->string('affiliate_referral_code', 64)->nullable()->after('affiliate_click_id');
            $table->decimal('affiliate_commission_rate', 5, 2)->nullable()->after('affiliate_referral_code');
            $table->decimal('affiliate_commission_amount', 10, 2)->nullable()->after('affiliate_commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('tts_product_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_profile_id');
            $table->dropConstrainedForeignId('affiliate_click_id');
            $table->dropColumn(['affiliate_referral_code', 'affiliate_commission_rate', 'affiliate_commission_amount']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_profile_id');
            $table->dropConstrainedForeignId('affiliate_click_id');
            $table->dropColumn(['affiliate_referral_code', 'affiliate_commission_rate', 'affiliate_commission_amount']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_profile_id');
            $table->dropConstrainedForeignId('affiliate_click_id');
            $table->dropColumn(['affiliate_referral_code', 'affiliate_commission_rate', 'affiliate_commission_amount']);
        });

        Schema::dropIfExists('affiliate_conversions');
        Schema::dropIfExists('affiliate_payouts');
        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('affiliate_profiles');
    }
};
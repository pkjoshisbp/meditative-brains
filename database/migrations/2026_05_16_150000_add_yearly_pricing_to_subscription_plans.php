<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('yearly_price', 10, 2)->nullable()->after('student_inr_price');
            $table->decimal('yearly_inr_price', 10, 2)->nullable()->after('yearly_price');
            $table->decimal('yearly_student_price', 10, 2)->nullable()->after('yearly_inr_price');
            $table->decimal('yearly_student_inr_price', 10, 2)->nullable()->after('yearly_student_price');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([
                'yearly_price',
                'yearly_inr_price',
                'yearly_student_price',
                'yearly_student_inr_price',
            ]);
        });
    }
};
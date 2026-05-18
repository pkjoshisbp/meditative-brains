<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_status', 20)->default('none')->after('remember_token');
            $table->timestamp('student_expires_at')->nullable()->after('student_status');
            $table->timestamp('student_verified_at')->nullable()->after('student_expires_at');
            $table->timestamp('student_reviewed_at')->nullable()->after('student_verified_at');
            $table->foreignId('student_reviewed_by')->nullable()->after('student_reviewed_at')->constrained('users')->nullOnDelete();
            $table->string('student_institution')->nullable()->after('student_reviewed_by');
            $table->string('student_id_number')->nullable()->after('student_institution');
            $table->string('student_document_path')->nullable()->after('student_id_number');
            $table->timestamp('student_document_uploaded_at')->nullable()->after('student_document_path');
            $table->text('student_review_notes')->nullable()->after('student_document_uploaded_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('student_price', 10, 2)->nullable()->after('sale_price');
            $table->decimal('student_inr_price', 10, 2)->nullable()->after('student_price');
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('student_price', 10, 2)->nullable()->after('inr_price');
            $table->decimal('student_inr_price', 10, 2)->nullable()->after('student_price');
        });

        Schema::table('tts_audio_products', function (Blueprint $table) {
            $table->decimal('student_audio_price', 10, 2)->nullable()->after('bundle_price_inr');
            $table->decimal('student_audio_price_inr', 10, 2)->nullable()->after('student_audio_price');
            $table->decimal('student_pdf_price', 10, 2)->nullable()->after('student_audio_price_inr');
            $table->decimal('student_pdf_price_inr', 10, 2)->nullable()->after('student_pdf_price');
            $table->decimal('student_bundle_price', 10, 2)->nullable()->after('student_pdf_price_inr');
            $table->decimal('student_bundle_price_inr', 10, 2)->nullable()->after('student_bundle_price');
        });
    }

    public function down(): void
    {
        Schema::table('tts_audio_products', function (Blueprint $table) {
            $table->dropColumn([
                'student_audio_price',
                'student_audio_price_inr',
                'student_pdf_price',
                'student_pdf_price_inr',
                'student_bundle_price',
                'student_bundle_price_inr',
            ]);
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['student_price', 'student_inr_price']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['student_price', 'student_inr_price']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_reviewed_by');
            $table->dropColumn([
                'student_status',
                'student_expires_at',
                'student_verified_at',
                'student_reviewed_at',
                'student_institution',
                'student_id_number',
                'student_document_path',
                'student_document_uploaded_at',
                'student_review_notes',
            ]);
        });
    }
};
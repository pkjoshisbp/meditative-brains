<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tts_audio_products', function (Blueprint $table) {
            if (!Schema::hasColumn('tts_audio_products', 'backend_category_name')) {
                $table->string('backend_category_name')->nullable()->after('backend_category_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tts_audio_products', function (Blueprint $table) {
            if (Schema::hasColumn('tts_audio_products', 'backend_category_name')) {
                $table->dropColumn('backend_category_name');
            }
        });
    }
};
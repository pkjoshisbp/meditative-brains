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
        Schema::table('tts_audio_products', function (Blueprint $table) {
            $table->string('backend_category_id')->nullable()->after('category');
            $table->index('backend_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tts_audio_products', function (Blueprint $table) {
            $table->dropIndex(['backend_category_id']);
            $table->dropColumn('backend_category_id');
        });
    }
};

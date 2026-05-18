<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tts_audiobooks', function (Blueprint $table) {
            $table->string('variant_name', 120)->nullable()->after('variant_key');
        });
    }

    public function down(): void
    {
        Schema::table('tts_audiobooks', function (Blueprint $table) {
            $table->dropColumn('variant_name');
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tts_audiobook_chapters', function (Blueprint $table) {
            if (!Schema::hasColumn('tts_audiobook_chapters', 'chunk_manifest')) {
                $table->json('chunk_manifest')->nullable()->after('ssml_content');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tts_audiobook_chapters', function (Blueprint $table) {
            if (Schema::hasColumn('tts_audiobook_chapters', 'chunk_manifest')) {
                $table->dropColumn('chunk_manifest');
            }
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tts_audio_products', function (Blueprint $table) {
            if (!Schema::hasColumn('tts_audio_products', 'html_book_path')) {
                $table->string('html_book_path', 500)->nullable()->after('pdf_file_url');
            }

            if (!Schema::hasColumn('tts_audio_products', 'html_book_url')) {
                $table->string('html_book_url', 500)->nullable()->after('html_book_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tts_audio_products', function (Blueprint $table) {
            $dropColumns = [];

            if (Schema::hasColumn('tts_audio_products', 'html_book_path')) {
                $dropColumns[] = 'html_book_path';
            }

            if (Schema::hasColumn('tts_audio_products', 'html_book_url')) {
                $dropColumns[] = 'html_book_url';
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};

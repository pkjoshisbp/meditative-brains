<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'linked_audiobook_id')) {
                $table->unsignedBigInteger('linked_audiobook_id')->nullable()->after('audio_path')->index();
            }

            if (!Schema::hasColumn('products', 'pdf_file_path')) {
                $table->string('pdf_file_path', 500)->nullable()->after('full_file');
            }

            if (!Schema::hasColumn('products', 'pdf_file_url')) {
                $table->string('pdf_file_url', 500)->nullable()->after('pdf_file_path');
            }
        });

        Schema::table('tts_audio_products', function (Blueprint $table) {
            if (!Schema::hasColumn('tts_audio_products', 'linked_audiobook_id')) {
                $table->unsignedBigInteger('linked_audiobook_id')->nullable()->after('pdf_file_url')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $dropColumns = [];

            if (Schema::hasColumn('products', 'linked_audiobook_id')) {
                $table->dropIndex(['linked_audiobook_id']);
                $dropColumns[] = 'linked_audiobook_id';
            }

            if (Schema::hasColumn('products', 'pdf_file_path')) {
                $dropColumns[] = 'pdf_file_path';
            }

            if (Schema::hasColumn('products', 'pdf_file_url')) {
                $dropColumns[] = 'pdf_file_url';
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });

        Schema::table('tts_audio_products', function (Blueprint $table) {
            if (Schema::hasColumn('tts_audio_products', 'linked_audiobook_id')) {
                $table->dropIndex(['linked_audiobook_id']);
                $table->dropColumn('linked_audiobook_id');
            }
        });
    }
};
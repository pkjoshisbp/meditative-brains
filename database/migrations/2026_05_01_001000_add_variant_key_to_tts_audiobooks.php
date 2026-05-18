<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tts_audiobooks', function (Blueprint $table) {
            $table->string('variant_key', 40)->nullable()->after('book_title');
        });

        DB::table('tts_audiobooks')
            ->select('id', 'engine', 'language', 'speaker', 'speaker_style', 'speaker_personality', 'expression_style')
            ->orderBy('id')
            ->get()
            ->each(function ($book) {
                $parts = [
                    strtolower(trim((string) ($book->engine ?? 'azure'))),
                    strtolower(trim((string) ($book->language ?? 'en-US'))),
                    strtolower(trim((string) ($book->speaker ?? 'en-US-AriaNeural'))),
                    strtolower(trim((string) ($book->speaker_style ?? ''))),
                    strtolower(trim((string) ($book->speaker_personality ?? ''))),
                    strtolower(trim((string) ($book->expression_style ?? ''))),
                ];

                DB::table('tts_audiobooks')
                    ->where('id', $book->id)
                    ->update(['variant_key' => sha1(implode('|', $parts))]);
            });

        Schema::table('tts_audiobooks', function (Blueprint $table) {
            $table->dropUnique('tts_audiobooks_book_title_unique');
            $table->index('book_title');
            $table->unique(['book_title', 'variant_key'], 'tts_audiobooks_title_variant_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tts_audiobooks', function (Blueprint $table) {
            $table->dropUnique('tts_audiobooks_title_variant_unique');
            $table->dropIndex(['book_title']);
            $table->dropColumn('variant_key');
            $table->unique('book_title');
        });
    }
};
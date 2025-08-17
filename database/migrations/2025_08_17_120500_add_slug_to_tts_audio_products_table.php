<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tts_audio_products')) {
            return; // base table missing; nothing to do
        }

        Schema::table('tts_audio_products', function (Blueprint $table) {
            if (!Schema::hasColumn('tts_audio_products', 'slug')) {
                $table->string('slug')->nullable()->unique()->after('name');
            }
        });

        // Backfill slugs if newly added
        if (Schema::hasColumn('tts_audio_products', 'slug')) {
            $products = DB::table('tts_audio_products')->whereNull('slug')->get();
            foreach ($products as $product) {
                $base = Str::slug($product->name ?: 'audio');
                $slug = $base;
                $i = 1;
                while (DB::table('tts_audio_products')->where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$product->id.'-'.$i;
                    $i++;
                }
                DB::table('tts_audio_products')->where('id', $product->id)->update(['slug' => $slug]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('tts_audio_products')) {
            return;
        }
        Schema::table('tts_audio_products', function (Blueprint $table) {
            if (Schema::hasColumn('tts_audio_products', 'slug')) {
                $table->dropUnique(['slug']);
                $table->dropColumn('slug');
            }
        });
    }
};

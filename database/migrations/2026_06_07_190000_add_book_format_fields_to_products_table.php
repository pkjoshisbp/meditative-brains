<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'content_type')) {
                $table->string('content_type', 30)->default('audio')->after('type')->index();
            }

            if (! Schema::hasColumn('products', 'related_audio_product_id')) {
                $table->unsignedBigInteger('related_audio_product_id')->nullable()->after('linked_audiobook_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $dropColumns = [];

            if (Schema::hasColumn('products', 'related_audio_product_id')) {
                $table->dropIndex(['related_audio_product_id']);
                $dropColumns[] = 'related_audio_product_id';
            }

            if (Schema::hasColumn('products', 'content_type')) {
                $table->dropIndex(['content_type']);
                $dropColumns[] = 'content_type';
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};

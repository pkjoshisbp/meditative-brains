<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('tts_audio_product_id')->nullable();
            $table->string('device_uuid',100)->nullable();
            $table->bigInteger('bytes')->nullable();
            $table->string('sha256',64)->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id','product_id']);
            $table->index(['user_id','tts_audio_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_downloads');
    }
};

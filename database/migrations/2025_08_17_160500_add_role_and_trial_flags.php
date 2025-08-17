<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function(Blueprint $table){
            if (!Schema::hasColumn('users','role')) {
                $table->string('role')->nullable()->after('device_limit');
            }
        });
        Schema::table('subscriptions', function(Blueprint $table){
            if (!Schema::hasColumn('subscriptions','is_trial')) {
                $table->boolean('is_trial')->default(false)->after('auto_renew');
            }
        });
    }
    public function down(): void {
        Schema::table('users', function(Blueprint $table){
            if (Schema::hasColumn('users','role')) { $table->dropColumn('role'); }
        });
        Schema::table('subscriptions', function(Blueprint $table){
            if (Schema::hasColumn('subscriptions','is_trial')) { $table->dropColumn('is_trial'); }
        });
    }
};

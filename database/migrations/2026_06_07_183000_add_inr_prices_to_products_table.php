<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'inr_price')) {
                $table->decimal('inr_price', 10, 2)->nullable()->after('sale_price');
            }

            if (! Schema::hasColumn('products', 'inr_sale_price')) {
                $table->decimal('inr_sale_price', 10, 2)->nullable()->after('inr_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('products', 'inr_sale_price')) {
                $columns[] = 'inr_sale_price';
            }

            if (Schema::hasColumn('products', 'inr_price')) {
                $columns[] = 'inr_price';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

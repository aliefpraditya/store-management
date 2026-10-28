<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Buying a box and buying a single can may cost differently, so every unit keeps its own purchase
     * price. Existing rows start from what they were worth before: the base cost times the conversion.
     */
    public function up(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->decimal('cost_price', 15, 2)->default(0)->after('selling_price');
        });

        DB::statement(
            'update product_units
                join products on products.id = product_units.product_id
                set product_units.cost_price = round(products.cost_price * product_units.conversion_factor, 2)',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
    }
};

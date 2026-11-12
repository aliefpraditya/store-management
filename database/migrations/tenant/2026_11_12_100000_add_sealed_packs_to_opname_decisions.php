<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Where the units of a damaged or returned item go is decided in the base unit, and these say how many whole sealed packs
     * (rolls of wire) each part holds, so a roll sent to dead stock or to the DG product stays a roll there. Null means the
     * admin did not say: nothing for dead stock and DG, and for the shelf the rolls that fit.
     */
    public function up(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->unsignedInteger('dead_stock_packs')->nullable()->after('damaged_goods_price');
            $table->unsignedInteger('damaged_goods_packs')->nullable()->after('dead_stock_packs');
            $table->unsignedInteger('full_price_packs')->nullable()->after('damaged_goods_packs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropColumn(['dead_stock_packs', 'damaged_goods_packs', 'full_price_packs']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A unit marked `is_sealed_pack` (a roll of wire) is a pack that cannot be put back together once cut. Its product's
     * stock stays one quantity in the base unit, and `stocks.intact_packs` counts how many whole packs are still in it; the
     * rest (quantity - packs x conversion) is loose. Everything else about the pack is derived from those two numbers.
     */
    public function up(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->boolean('is_sealed_pack')->default(false)->after('is_default_sale');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->unsignedInteger('intact_packs')->default(0)->after('quantity');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->integer('pack_change')->default(0)->after('balance_after');
            $table->unsignedInteger('packs_after')->default(0)->after('pack_change');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->boolean('is_sealed_pack')->default(false)->after('conversion_snapshot');
        });

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->unsignedInteger('counted_packs')->nullable()->after('counted_quantity');
            $table->unsignedInteger('system_packs_at_posting')->nullable()->after('system_quantity_at_posting');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropColumn(['counted_packs', 'system_packs_at_posting']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('is_sealed_pack');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['pack_change', 'packs_after']);
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('intact_packs');
        });

        Schema::table('product_units', function (Blueprint $table) {
            $table->dropColumn('is_sealed_pack');
        });
    }
};

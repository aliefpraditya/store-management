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
     * An order line for a sealed pack (a roll of wire) sets whole packs aside, not just metres: a sale of a roll must not
     * take a roll that a ready order is waiting for. Like `sale_items.is_sealed_pack`, the flag is copied when the order is
     * made. Lines of orders made before this are marked from the unit's current flag, the best that can be known now.
     */
    public function up(): void
    {
        Schema::table('customer_order_items', function (Blueprint $table) {
            $table->boolean('is_sealed_pack')->default(false)->after('conversion_snapshot');
        });

        DB::table('customer_order_items')
            ->whereIn('product_unit_id', DB::table('product_units')->where('is_sealed_pack', true)->select('id'))
            ->update(['is_sealed_pack' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_order_items', function (Blueprint $table) {
            $table->dropColumn('is_sealed_pack');
        });
    }
};

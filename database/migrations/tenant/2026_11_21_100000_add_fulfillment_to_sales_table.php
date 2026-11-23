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
     * Whether the goods of a receipt are taken at the store or delivered. Receipts made before this column existed that
     * were paid on delivery (COD) or already have a delivery note were delivered; the rest were taken at the store.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('fulfillment', ['in_store', 'delivery'])->default('in_store')->after('status');
            $table->index('fulfillment');
        });

        DB::table('sales')
            ->where(fn ($sales) => $sales
                ->whereExists(fn ($payments) => $payments->select(DB::raw(1))->from('sale_payments')->whereColumn('sale_payments.sale_id', 'sales.id')->where('sale_payments.method', 'cod'))
                ->orWhereExists(fn ($notes) => $notes->select(DB::raw(1))->from('delivery_note_sales')->whereColumn('delivery_note_sales.sale_id', 'sales.id')))
            ->update(['fulfillment' => 'delivery']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['fulfillment']);
            $table->dropColumn('fulfillment');
        });
    }
};

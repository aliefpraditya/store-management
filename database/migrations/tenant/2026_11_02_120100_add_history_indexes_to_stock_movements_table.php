<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The stock history lists the newest movements first. The existing index starts with the product, so a list
     * without a product, or with only a warehouse or a date range, had to sort the whole ledger.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['moved_at', 'id']);
            $table->index(['warehouse_id', 'moved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['moved_at', 'id']);
            $table->dropIndex(['warehouse_id', 'moved_at']);
        });
    }
};

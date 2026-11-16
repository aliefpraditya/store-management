<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The receipt history filters by cashier and lists the cashiers who sold anything (`distinct cashier_id,
     * cashier_name`); with this index both are answered from the index instead of the whole receipts table.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['cashier_id', 'cashier_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['cashier_id', 'cashier_name']);
        });
    }
};

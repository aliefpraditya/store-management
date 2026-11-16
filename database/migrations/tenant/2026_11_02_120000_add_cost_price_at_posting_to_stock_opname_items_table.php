<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The cost price the item's variance was valued at when the opname was posted, so the value of a posted opname
     * does not change when the product's cost price is edited later. Null until posted.
     */
    public function up(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->decimal('cost_price_at_posting', 15, 2)->nullable()->after('system_quantity_at_posting');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropColumn('cost_price_at_posting');
        });
    }
};

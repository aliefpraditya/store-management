<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An item has no barcode of its own any more: the sticker is printed from its SKU, and the till finds an item by its SKU.
     */
    public function up(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->dropColumn('barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->string('barcode')->nullable()->unique()->after('is_base');
        });
    }
};

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
     * A whole-store opname is no longer offered, so `all` goes away; an opname that already had it keeps its
     * items and becomes a "selected products" opname. The `category` scope now means category and/or brand.
     * The daily-check reason is renamed from `daily_check` to `routine`.
     */
    public function up(): void
    {
        DB::table('stock_opnames')->where('scope', 'all')->update(['scope' => 'selected']);
        DB::statement("ALTER TABLE stock_opnames MODIFY scope ENUM('category', 'selected') NOT NULL");

        DB::table('stock_opnames')->where('reason_code', 'daily_check')->update(['reason_code' => 'routine']);

        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('category_id')->constrained();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });

        DB::table('stock_opnames')->where('reason_code', 'routine')->update(['reason_code' => 'daily_check']);

        DB::statement("ALTER TABLE stock_opnames MODIFY scope ENUM('all', 'category', 'selected') NOT NULL");
    }
};

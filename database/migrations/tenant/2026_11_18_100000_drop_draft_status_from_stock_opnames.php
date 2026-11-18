<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An opname is created already counting, so `draft` goes away. A draft that already exists never took a snapshot and
     * counted nothing; it is erased, the same as cancelling a draft always did.
     */
    public function up(): void
    {
        $draftIds = DB::table('stock_opnames')->where('status', 'draft')->pluck('id');

        DB::table('stock_opname_items')->whereIn('stock_opname_id', $draftIds)->delete();
        DB::table('stock_opnames')->whereIn('id', $draftIds)->delete();

        DB::statement("ALTER TABLE stock_opnames MODIFY status ENUM('counting', 'review', 'posted', 'cancelled') NOT NULL DEFAULT 'counting'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE stock_opnames MODIFY status ENUM('draft', 'counting', 'review', 'posted', 'cancelled') NOT NULL DEFAULT 'draft'");
    }
};

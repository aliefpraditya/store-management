<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A purchase is received once, by the warehouse, so it no longer has a "received in part" or "short closed" state:
     * draft → ordered → shipped → completed, or cancelled. What arrived short or damaged is recorded on the receiving, not
     * as an open remainder on the purchase, so the per-line received quantity goes too. No code ever set the removed
     * statuses, but any such row is moved to the nearest remaining one so the column can be narrowed.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE purchases MODIFY status ENUM('draft', 'ordered', 'partially_received', 'received', 'closed', 'shipped', 'completed', 'cancelled') NOT NULL DEFAULT 'draft'");
        DB::table('purchases')->where('status', 'partially_received')->update(['status' => 'shipped']);
        DB::table('purchases')->whereIn('status', ['received', 'closed'])->update(['status' => 'completed']);
        DB::statement("ALTER TABLE purchases MODIFY status ENUM('draft', 'ordered', 'shipped', 'completed', 'cancelled') NOT NULL DEFAULT 'draft'");

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn('received_base_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('received_base_quantity', 15, 3)->default(0)->after('subtotal');
        });

        DB::statement("ALTER TABLE purchases MODIFY status ENUM('draft', 'ordered', 'partially_received', 'received', 'closed', 'shipped', 'completed', 'cancelled') NOT NULL DEFAULT 'draft'");
        DB::table('purchases')->where('status', 'shipped')->update(['status' => 'ordered']);
        DB::table('purchases')->where('status', 'completed')->update(['status' => 'received']);
        DB::statement("ALTER TABLE purchases MODIFY status ENUM('draft', 'ordered', 'partially_received', 'received', 'closed', 'cancelled') NOT NULL DEFAULT 'draft'");
    }
};

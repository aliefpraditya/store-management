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
     * Returned goods no longer go straight back on the shelf or to dead stock: they wait in a returns warehouse, paired with
     * the sellable warehouse they were sold from (`returns_for_warehouse_id`), until an opname of that warehouse inspects
     * them and releases them. So a return item has no disposition any more (old ones keep theirs), and every sellable
     * warehouse the store already has gets its returns warehouse.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE warehouses MODIFY type ENUM('sellable', 'dead_stock', 'returns') NOT NULL");

        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('returns_for_warehouse_id')->nullable()->after('type')->constrained('warehouses');
        });

        DB::statement("ALTER TABLE sale_return_items MODIFY disposition ENUM('resellable', 'dead_stock') NULL");

        $sellableWarehouses = DB::table('warehouses')->where('type', 'sellable')->orderBy('id')->get();

        foreach ($sellableWarehouses as $warehouse) {
            DB::table('warehouses')->insert([
                'code' => $sellableWarehouses->count() === 1 ? 'RETUR' : "RETUR-{$warehouse->code}",
                'name' => "Retur {$warehouse->name}",
                'type' => 'returns',
                'returns_for_warehouse_id' => $warehouse->id,
                'is_default' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('sale_return_items')->whereNull('disposition')->update(['disposition' => 'resellable']);
        DB::statement("ALTER TABLE sale_return_items MODIFY disposition ENUM('resellable', 'dead_stock') NOT NULL");

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('returns_for_warehouse_id');
        });

        DB::table('warehouses')->where('type', 'returns')->delete();
        DB::statement("ALTER TABLE warehouses MODIFY type ENUM('sellable', 'dead_stock') NOT NULL");
    }
};

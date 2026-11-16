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
     * The reason of a variance becomes a list the store manages (four come with every store), instead of free text. The
     * old free text stays on the item as its note. An item also records that an admin reviewed it and, for damaged
     * goods, where the damaged units go: dead stock, a new "DG" product that is sold at a lower price, or back on the
     * shelf at full price. Products learn whether they are damaged goods and which product they were made from.
     */
    public function up(): void
    {
        Schema::create('opname_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->enum('behavior', ['plain', 'damaged'])->default('plain');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('opname_reasons')->insert([
            ['name' => 'Pembelian', 'behavior' => 'plain', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cek stok', 'behavior' => 'plain', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Hilang', 'behavior' => 'plain', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Rusak', 'behavior' => 'damaged', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->renameColumn('reason', 'note');
        });

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->foreignId('reason_id')->nullable()->after('variance')->constrained('opname_reasons');
            $table->dateTime('reviewed_at')->nullable()->after('note');
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
            $table->decimal('dead_stock_quantity', 15, 3)->nullable()->after('reviewed_by');
            $table->decimal('damaged_goods_quantity', 15, 3)->nullable()->after('dead_stock_quantity');
            $table->decimal('full_price_quantity', 15, 3)->nullable()->after('damaged_goods_quantity');
            $table->decimal('damaged_goods_price', 15, 2)->nullable()->after('full_price_quantity');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_damaged_goods')->default(false)->after('is_active')->index();
            $table->foreignId('damaged_from_product_id')->nullable()->after('is_damaged_goods')->constrained('products')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('damaged_from_product_id');
            $table->dropColumn('is_damaged_goods');
        });

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reason_id');
            $table->dropColumn(['reviewed_at', 'reviewed_by', 'dead_stock_quantity', 'damaged_goods_quantity', 'full_price_quantity', 'damaged_goods_price']);
        });

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->renameColumn('note', 'reason');
        });

        Schema::dropIfExists('opname_reasons');
    }
};

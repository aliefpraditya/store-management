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
     * A delivery note belongs to one receipt (`delivery_notes.sale_id`), but it can also carry goods of the receipt that was
     * made together with a customer order, so the customer is delivered once. This lists every receipt that has goods on a
     * note, so each receipt can tell which notes carry its goods: whether they were delivered, whether it can still be
     * voided. Existing notes carry the goods of their own receipt only.
     */
    public function up(): void
    {
        Schema::create('delivery_note_sales', function (Blueprint $table) {
            $table->foreignId('delivery_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained();

            $table->primary(['delivery_note_id', 'sale_id']);
            $table->index('sale_id');
        });

        DB::statement('INSERT INTO delivery_note_sales (delivery_note_id, sale_id) SELECT id, sale_id FROM delivery_notes');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_note_sales');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A delivery note never moves stock: stock already left when the receipt was made.
     */
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('sale_id')->constrained();
            $table->date('business_date');
            $table->enum('status', ['draft', 'shipped', 'delivered', 'partially_delivered', 'returned', 'cancelled'])->default('draft');
            $table->string('recipient_name');
            $table->string('recipient_phone')->nullable();
            $table->text('recipient_address')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('vehicle_number')->nullable();
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('print_count')->default(0);
            $table->unsignedBigInteger('created_by');
            $table->string('created_by_name');
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('delivery_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_unit_id')->constrained();
            $table->string('product_name');
            $table->string('unit_name');
            $table->decimal('quantity', 15, 3);
            $table->decimal('quantity_delivered', 15, 3)->default(0);
            $table->decimal('quantity_returned', 15, 3)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_note_items');
        Schema::dropIfExists('delivery_notes');
    }
};

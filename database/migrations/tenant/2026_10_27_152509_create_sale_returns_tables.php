<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('register_id')->constrained();
            $table->foreignId('cash_shift_id')->constrained();
            $table->dateTime('returned_at');
            $table->enum('resolution', ['refund', 'exchange']);
            $table->enum('refund_method', ['cash', 'transfer', 'qris', 'edc'])->nullable();
            $table->decimal('credit_amount', 15, 2);
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->foreignId('exchange_sale_id')->nullable()->constrained('sales');
            $table->enum('status', ['pending', 'completed', 'rejected'])->default('pending');
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_unit_id')->constrained();
            $table->decimal('quantity', 15, 3);
            $table->decimal('base_quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('cost_total', 15, 2)->default(0);
            $table->decimal('refund_subtotal', 15, 2);
            $table->enum('disposition', ['resellable', 'dead_stock']);
            $table->foreignId('destination_warehouse_id')->constrained('warehouses');
            $table->string('condition_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Receipts are never deleted: a mistake is corrected by voiding. `client_uuid` makes a double
     * submit from the cashier harmless.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->string('number')->unique();
            $table->foreignId('register_id')->constrained();
            $table->foreignId('cash_shift_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->dateTime('sold_at');
            $table->date('business_date');
            $table->unsignedBigInteger('cashier_id');
            $table->string('cashier_name');
            $table->string('customer_name')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->decimal('paid_total', 15, 2);
            $table->decimal('change_amount', 15, 2)->default(0);
            $table->enum('status', ['completed', 'voided'])->default('completed');
            $table->enum('return_status', ['none', 'partial', 'full'])->default('none');
            $table->string('void_reason')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->unsignedInteger('print_count')->default(0);
            $table->string('legacy_number')->nullable();
            $table->decimal('legacy_unpaid_amount', 15, 2)->default(0);
            $table->boolean('is_price_estimated')->default(false);
            $table->timestamps();

            $table->index(['business_date', 'status']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_unit_id')->constrained();
            $table->string('product_name');
            $table->string('unit_name');
            $table->decimal('quantity', 15, 3);
            $table->decimal('conversion_snapshot', 15, 3);
            $table->decimal('base_quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('cost_total', 15, 2)->default(0);
            $table->boolean('is_cost_estimated')->default(false);
            $table->boolean('is_manual_price')->default(false);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['cash', 'transfer', 'qris', 'edc', 'return_credit']);
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_cancellations', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('register_id')->constrained();
            $table->foreignId('cash_shift_id')->constrained();
            $table->unsignedBigInteger('cashier_id');
            $table->string('cashier_name');
            $table->dateTime('cancelled_at');
            $table->unsignedInteger('item_count');
            $table->decimal('total', 15, 2);
            $table->string('reason')->nullable();
            $table->json('items_snapshot');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_cancellations');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};

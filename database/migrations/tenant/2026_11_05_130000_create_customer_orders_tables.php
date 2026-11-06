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
     * A customer order (PO) is goods the store promised but does not have yet. It is not a sale: it moves no stock and
     * is no revenue. The deposit (DP) is real money in or out of a shift's drawer, kept in `customer_order_payments`.
     * When the customer collects the goods a normal receipt is made, and the deposit goes onto it as the
     * `order_deposit` payment method (not new money, like `return_credit`).
     */
    public function up(): void
    {
        Schema::create('customer_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->string('number')->unique();
            $table->foreignId('register_id')->constrained();
            $table->foreignId('cash_shift_id')->constrained('cash_shifts');
            $table->foreignId('warehouse_id')->constrained();
            $table->enum('status', ['ordered', 'ready', 'completed', 'cancelled'])->default('ordered');
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('source_note')->nullable();
            $table->date('expected_date')->nullable();
            $table->decimal('total', 15, 2);
            $table->dateTime('ordered_at');
            $table->date('business_date');
            $table->dateTime('ready_at')->nullable();
            $table->foreignId('origin_sale_id')->nullable()->constrained('sales');
            $table->foreignId('sale_id')->nullable()->constrained('sales');
            $table->string('cancel_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedInteger('print_count')->default(0);
            $table->unsignedBigInteger('created_by');
            $table->string('created_by_name');
            $table->timestamps();

            $table->index(['status', 'warehouse_id']);
        });

        Schema::create('customer_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_unit_id')->constrained();
            $table->string('product_name');
            $table->string('unit_name');
            $table->decimal('quantity', 15, 3);
            $table->decimal('conversion_snapshot', 15, 3);
            $table->decimal('base_quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });

        Schema::create('customer_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_shift_id')->constrained('cash_shifts');
            $table->enum('kind', ['deposit', 'refund']);
            $table->enum('method', ['cash', 'transfer', 'qris', 'edc']);
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->dateTime('paid_at');
            $table->timestamps();

            $table->index(['cash_shift_id', 'kind', 'method']);
        });

        DB::statement("ALTER TABLE sale_payments MODIFY method ENUM('cash', 'transfer', 'qris', 'edc', 'return_credit', 'cod', 'order_deposit') NOT NULL");

        Schema::table('daily_closings', function (Blueprint $table) {
            $table->decimal('order_deposits_received', 15, 2)->default(0)->after('sales_cod');
            $table->decimal('order_deposits_refunded', 15, 2)->default(0)->after('order_deposits_received');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_closings', function (Blueprint $table) {
            $table->dropColumn(['order_deposits_received', 'order_deposits_refunded']);
        });

        DB::statement("ALTER TABLE sale_payments MODIFY method ENUM('cash', 'transfer', 'qris', 'edc', 'return_credit', 'cod') NOT NULL");

        Schema::dropIfExists('customer_order_payments');
        Schema::dropIfExists('customer_order_items');
        Schema::dropIfExists('customer_orders');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A purchase (nota) is what is ordered from one supplier; it changes no stock. A line keeps the unit code and the
     * conversion it was bought with rather than pointing at `product_units`, so editing the product later does not change
     * what an old nota says. `created_by` and `confirmed_by` are central user ids (no foreign key across databases).
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->string('number')->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->string('supplier_document_number')->nullable();
            $table->date('purchase_date');
            $table->date('expected_date')->nullable();
            $table->foreignId('warehouse_id')->constrained();
            $table->enum('status', ['draft', 'ordered', 'partially_received', 'received', 'closed', 'cancelled'])->default('draft');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->string('created_by_name');
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->string('confirmed_by_name')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'purchase_date']);
            $table->index('status');
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->string('unit_code', 20);
            $table->decimal('conversion_factor', 15, 3);
            $table->decimal('quantity', 15, 3);
            $table->decimal('base_quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('base_unit_cost', 15, 4);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('received_base_quantity', 15, 3)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['purchase_id', 'product_id', 'unit_code']);
        });

        Schema::create('purchase_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_attachments');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
    }
};

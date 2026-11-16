<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * User columns have no foreign key: users live in the central database.
     */
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('warehouse_id')->constrained();
            $table->date('opname_date');
            $table->enum('scope', ['all', 'category', 'selected']);
            $table->foreignId('category_id')->nullable()->constrained();
            $table->enum('status', ['draft', 'counting', 'review', 'posted', 'cancelled'])->default('draft');
            $table->dateTime('snapshot_at')->nullable();
            $table->string('reason_code')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('posted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'opname_date']);
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('snapshot_quantity', 15, 3);
            $table->decimal('counted_quantity', 15, 3)->nullable();
            $table->decimal('system_quantity_at_posting', 15, 3)->nullable();
            $table->decimal('variance', 15, 3)->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['stock_opname_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
    }
};

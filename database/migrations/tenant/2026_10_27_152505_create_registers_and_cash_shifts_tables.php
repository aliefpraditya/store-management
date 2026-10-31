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
        Schema::create('registers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->foreignId('warehouse_id')->constrained();
            $table->decimal('fixed_opening_cash', 15, 2)->default(0);
            $table->unsignedInteger('last_sale_sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cash_shifts', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('register_id')->constrained();
            $table->date('business_date');
            $table->unsignedBigInteger('cashier_id');
            $table->string('cashier_name');
            $table->dateTime('opened_at');
            $table->decimal('opening_cash', 15, 2);
            $table->dateTime('closed_at')->nullable();
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->decimal('counted_cash', 15, 2)->nullable();
            $table->decimal('cash_difference', 15, 2)->nullable();
            $table->decimal('deposit_amount', 15, 2)->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->enum('difference_status', ['none', 'pending_approval', 'approved', 'rejected'])->default('none');
            $table->unsignedBigInteger('difference_approved_by')->nullable();
            $table->dateTime('difference_approved_at')->nullable();
            $table->text('closing_note')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamps();

            $table->index(['register_id', 'status']);
            $table->index('business_date');
            $table->index('difference_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_shifts');
        Schema::dropIfExists('registers');
    }
};

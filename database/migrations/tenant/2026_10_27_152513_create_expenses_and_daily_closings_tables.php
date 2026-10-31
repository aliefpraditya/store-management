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
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->string('number')->unique();
            $table->date('business_date');
            $table->foreignId('cash_shift_id')->nullable()->constrained();
            $table->foreignId('expense_category_id')->constrained();
            $table->enum('source', ['drawer', 'other']);
            $table->string('description');
            $table->string('paid_to')->nullable();
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['recorded', 'voided'])->default('recorded');
            $table->string('void_reason')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->string('created_by_name');
            $table->timestamps();

            $table->index('business_date');
        });

        Schema::create('daily_closings', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->enum('status', ['closed', 'reopened'])->default('closed');
            $table->unsignedInteger('shifts_count')->default(0);
            $table->unsignedInteger('sales_count')->default(0);
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('sales_cash', 15, 2)->default(0);
            $table->decimal('sales_transfer', 15, 2)->default(0);
            $table->decimal('sales_qris', 15, 2)->default(0);
            $table->decimal('sales_edc', 15, 2)->default(0);
            $table->decimal('total_returns', 15, 2)->default(0);
            $table->decimal('refunds_cash', 15, 2)->default(0);
            $table->decimal('expenses_drawer', 15, 2)->default(0);
            $table->decimal('expenses_other', 15, 2)->default(0);
            $table->decimal('total_expected_cash', 15, 2)->default(0);
            $table->decimal('total_counted_cash', 15, 2)->default(0);
            $table->decimal('total_cash_difference', 15, 2)->default(0);
            $table->decimal('total_deposit', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('closed_by');
            $table->dateTime('closed_at');
            $table->unsignedBigInteger('reopened_by')->nullable();
            $table->dateTime('reopened_at')->nullable();
            $table->string('reopen_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};

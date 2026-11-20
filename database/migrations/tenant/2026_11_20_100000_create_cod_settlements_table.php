<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The money of a COD receipt, received once its goods were delivered. One settlement per receipt: what the
     * customer owed is the COD part minus what returns cut from it (`sale_returns.cod_offset_amount`), and what was
     * actually received may differ, with a reason, which then waits for an admin's approval.
     *
     * `cash_shift_id` is the shift of whoever received the money, which is the drawer a cash settlement lands in. It is
     * null when a user without a running shift received it by transfer, QRIS or EDC. The day closing saves what the day
     * received per method.
     */
    public function up(): void
    {
        Schema::create('cod_settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('sale_id')->unique()->constrained();
            $table->foreignId('cash_shift_id')->nullable()->constrained('cash_shifts');
            $table->date('business_date');
            $table->dateTime('received_at');
            $table->enum('method', ['cash', 'transfer', 'qris', 'edc']);
            $table->decimal('amount', 15, 2);
            $table->decimal('expected_amount', 15, 2);
            $table->string('difference_reason')->nullable();
            $table->string('reference')->nullable();
            $table->string('notes')->nullable();
            $table->enum('status', ['confirmed', 'awaiting_approval']);
            $table->unsignedBigInteger('received_by');
            $table->string('received_by_name');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->index('business_date');
            $table->index('status');
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->decimal('cod_offset_amount', 15, 2)->default(0)->after('refund_amount');
        });

        Schema::table('daily_closings', function (Blueprint $table) {
            $table->decimal('cod_received_cash', 15, 2)->default(0)->after('sales_cod');
            $table->decimal('cod_received_transfer', 15, 2)->default(0)->after('cod_received_cash');
            $table->decimal('cod_received_qris', 15, 2)->default(0)->after('cod_received_transfer');
            $table->decimal('cod_received_edc', 15, 2)->default(0)->after('cod_received_qris');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_closings', function (Blueprint $table) {
            $table->dropColumn(['cod_received_cash', 'cod_received_transfer', 'cod_received_qris', 'cod_received_edc']);
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropColumn('cod_offset_amount');
        });

        Schema::dropIfExists('cod_settlements');
    }
};

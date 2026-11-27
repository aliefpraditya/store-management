<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money a customer paid on a COD receipt before the goods were delivered. It is part of the bill, never all of it (a
     * receipt paid in full is no COD receipt), so what is left is still collected on delivery. Several may be taken per receipt.
     *
     * `cash_shift_id` is the shift of whoever received the money, which is the drawer a cash payment lands in. It is null when
     * a user without a running shift received it by transfer, QRIS or EDC.
     */
    public function up(): void
    {
        Schema::create('cod_advance_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('cash_shift_id')->nullable()->constrained('cash_shifts');
            $table->date('business_date');
            $table->dateTime('received_at');
            $table->enum('method', ['cash', 'transfer', 'qris', 'edc']);
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('received_by');
            $table->string('received_by_name');
            $table->timestamps();

            $table->index('business_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cod_advance_payments');
    }
};

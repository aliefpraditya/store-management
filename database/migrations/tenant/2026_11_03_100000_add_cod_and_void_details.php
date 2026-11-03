<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * COD (cash on delivery) becomes a payment method. A voided receipt keeps who voided it and, when part of it had
     * been paid in advance, what went back to the customer: the cash part comes out of the drawer of the shift that
     * voided it (`void_cash_shift_id`). The day closing saves what the day sold on COD and what it voided.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE sale_payments MODIFY method ENUM('cash', 'transfer', 'qris', 'edc', 'return_credit', 'cod') NOT NULL");

        Schema::table('sales', function (Blueprint $table) {
            $table->string('voided_by_name')->nullable()->after('voided_by');
            $table->foreignId('void_cash_shift_id')->nullable()->after('voided_at')->constrained('cash_shifts');
            $table->decimal('void_cash_refund', 15, 2)->default(0)->after('void_cash_shift_id');
            $table->string('void_refund_account')->nullable()->after('void_cash_refund');

            $table->index('voided_at');
        });

        Schema::table('daily_closings', function (Blueprint $table) {
            $table->decimal('sales_cod', 15, 2)->default(0)->after('sales_edc');
            $table->unsignedInteger('voids_count')->default(0)->after('refunds_cash');
            $table->decimal('total_voids', 15, 2)->default(0)->after('voids_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_closings', function (Blueprint $table) {
            $table->dropColumn(['sales_cod', 'voids_count', 'total_voids']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['voided_at']);
            $table->dropConstrainedForeignId('void_cash_shift_id');
            $table->dropColumn(['voided_by_name', 'void_cash_refund', 'void_refund_account']);
        });

        DB::statement("ALTER TABLE sale_payments MODIFY method ENUM('cash', 'transfer', 'qris', 'edc', 'return_credit') NOT NULL");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A void belongs to a business day like a sale or a return does: the business day of the shift of the one who voided
     * it, or the day it was done when that person had no shift. Before this the day closing and the sales report counted
     * it by the calendar day of `voided_at`, which differs from the shift's day when a shift runs past midnight, while
     * the cash it gave back was booked on the shift's day. Voids made before this column existed get the business day of
     * the shift that gave the cash back, else the calendar day of `voided_at`.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->date('void_business_date')->nullable()->after('voided_at');
            $table->index('void_business_date');
        });

        DB::statement("
            UPDATE sales
            LEFT JOIN cash_shifts ON cash_shifts.id = sales.void_cash_shift_id
            SET sales.void_business_date = COALESCE(cash_shifts.business_date, DATE(sales.voided_at))
            WHERE sales.status = 'voided' AND sales.voided_at IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['void_business_date']);
            $table->dropColumn('void_business_date');
        });
    }
};

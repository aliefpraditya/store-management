<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A purchase is paid once, in full, when it is confirmed ("Konfirmasi & Bayar"), so the payment is a few columns on the
     * purchase itself rather than a table of its own. `paid_date` stays empty for a nota that has nothing to pay (all lines
     * free). `paid_by` is a central user id (no foreign key across databases).
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->date('paid_date')->nullable()->after('confirmed_at');
            $table->decimal('paid_amount', 15, 2)->nullable()->after('paid_date');
            $table->enum('payment_method', ['transfer', 'cash', 'other'])->nullable()->after('paid_amount');
            $table->string('payment_notes')->nullable()->after('payment_method');
            $table->unsignedBigInteger('paid_by')->nullable()->after('payment_notes');
            $table->string('paid_by_name')->nullable()->after('paid_by');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['paid_date', 'paid_amount', 'payment_method', 'payment_notes', 'paid_by', 'paid_by_name']);
        });
    }
};

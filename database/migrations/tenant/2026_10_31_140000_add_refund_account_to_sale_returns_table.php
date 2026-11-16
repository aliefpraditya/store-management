<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Money paid back other than in cash goes to an account of the customer: bank, number and name, as typed.
     */
    public function up(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->string('refund_account')->nullable()->after('refund_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropColumn('refund_account');
        });
    }
};

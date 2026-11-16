<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An exchange asked for by a cashier waits for approval, and the replacement receipt is only made then. This
     * keeps what the customer chose and paid in the meantime: the priced replacement lines and the extra payments.
     */
    public function up(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->json('exchange_data')->nullable()->after('exchange_sale_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropColumn('exchange_data');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `closed_by` is the central user who counted the drawer and closed the shift: usually the cashier, but an admin may
     * close a shift the cashier forgot, and then must not decide its cash difference too. `counted_at` is when the drawer
     * was counted; `closed_at` stays empty while a counted shift waits for review and is set when the difference is
     * decided. Shifts closed before this existed took their count when they closed, and their cashier closed them.
     */
    public function up(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->unsignedBigInteger('closed_by')->nullable()->after('closed_at');
            $table->dateTime('counted_at')->nullable()->after('closed_by');
        });

        DB::statement("UPDATE cash_shifts SET counted_at = closed_at, closed_by = cashier_id WHERE status = 'closed' AND closed_at IS NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->dropColumn(['closed_by', 'counted_at']);
        });
    }
};

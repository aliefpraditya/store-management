<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The petty cash is no longer a fixed amount on the register. A shift records what it left in the drawer when it
     * closed, and the next shift on that register opens with it (or with another amount and a reason). Shifts closed
     * before this change deposited everything above their opening cash, so they left exactly their opening cash.
     */
    public function up(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->decimal('drawer_left_cash', 15, 2)->nullable()->after('deposit_amount');
            $table->string('opening_cash_note')->nullable()->after('opening_cash');
        });

        DB::table('cash_shifts')->where('status', 'closed')->update(['drawer_left_cash' => DB::raw('opening_cash')]);

        Schema::table('registers', function (Blueprint $table) {
            $table->dropColumn('fixed_opening_cash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->decimal('fixed_opening_cash', 15, 2)->default(0)->after('warehouse_id');
        });

        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->dropColumn(['drawer_left_cash', 'opening_cash_note']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * "One open shift per cashier" and "one open shift per register" were only checked in code, under a lock on the
     * register, so one cashier opening two registers at the same moment could end up with two. These generated columns
     * hold the cashier or register only while the shift is open, and a unique index on each makes the database refuse
     * a second open shift. Closed shifts have null there, and nulls never clash.
     */
    public function up(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->unsignedBigInteger('open_cashier_id')->nullable()->virtualAs("if(status = 'open', cashier_id, null)")->unique();
            $table->unsignedBigInteger('open_register_id')->nullable()->virtualAs("if(status = 'open', register_id, null)")->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->dropUnique(['open_cashier_id']);
            $table->dropUnique(['open_register_id']);
            $table->dropColumn(['open_cashier_id', 'open_register_id']);
        });
    }
};

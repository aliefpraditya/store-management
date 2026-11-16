<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A difference the cashier bears is final once the admin decides it: the report and the cashier paying the money back are
     * kept outside the system, so there is no follow-up left to track.
     */
    public function up(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->dropColumn(['followed_up_by', 'followed_up_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->unsignedBigInteger('followed_up_by')->nullable()->after('approval_note');
            $table->dateTime('followed_up_at')->nullable()->after('followed_up_by');
        });
    }
};

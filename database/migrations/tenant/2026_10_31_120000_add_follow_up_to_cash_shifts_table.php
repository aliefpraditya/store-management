<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A cash difference an admin refused stays on the admin's list until someone says it has been dealt with.
     */
    public function up(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->unsignedBigInteger('followed_up_by')->nullable()->after('approval_note');
            $table->dateTime('followed_up_at')->nullable()->after('followed_up_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table) {
            $table->dropColumn(['followed_up_by', 'followed_up_at']);
        });
    }
};

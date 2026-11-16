<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A shift whose counted cash differs from the expected cash is no longer simply closed: it waits for an admin's review
     * (`pending_review`) and only closes once the admin has decided. Shifts that were already closed with a difference
     * still waiting keep their status; deciding them just leaves them closed.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE cash_shifts MODIFY status ENUM('open', 'pending_review', 'closed') NOT NULL DEFAULT 'open'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('cash_shifts')->where('status', 'pending_review')->update(['status' => 'closed']);

        DB::statement("ALTER TABLE cash_shifts MODIFY status ENUM('open', 'closed') NOT NULL DEFAULT 'open'");
    }
};

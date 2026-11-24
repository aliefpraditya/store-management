<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const REASON_NAME = 'Dimusnahkan';

    /**
     * Run the migrations.
     *
     * Stock that is thrown away leaves a count as a variance of its own, apart from "Hilang" (goods that went missing),
     * so the variance report can tell a write-off from a loss. Stores seeded from now on get it with the other
     * reasons; this adds it to the stores that exist. A store whose admin already made a reason of that name keeps its
     * row, which becomes the system one, since the name is unique.
     */
    public function up(): void
    {
        $reason = DB::table('opname_reasons')->where('name', self::REASON_NAME);

        if ($reason->exists()) {
            $reason->update(['behavior' => 'plain', 'is_system' => true, 'is_active' => true, 'updated_at' => now()]);

            return;
        }

        DB::table('opname_reasons')->insert([
            'name' => self::REASON_NAME,
            'behavior' => 'plain',
            'is_system' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('opname_reasons')->where('name', self::REASON_NAME)->delete();
    }
};

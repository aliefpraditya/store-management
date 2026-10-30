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
     * A cancelled opname is soft deleted: it is hidden from the list but kept. Opnames cancelled before this
     * column existed are hidden the same way.
     */
    public function up(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->softDeletes();
        });

        DB::table('stock_opnames')->where('status', 'cancelled')->update(['deleted_at' => DB::raw('updated_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

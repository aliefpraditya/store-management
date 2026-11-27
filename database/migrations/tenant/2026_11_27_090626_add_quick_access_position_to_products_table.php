<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The place of the item among the store's quick access items, the tiles the till offers before anything is typed
     * (Alt and 1 to 9 add the first nine). It stays empty for an item that is not in quick access.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('quick_access_position')->nullable()->index()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['quick_access_position']);
            $table->dropColumn('quick_access_position');
        });
    }
};

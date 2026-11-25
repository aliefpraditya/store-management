<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a supplier is, and where a carrier takes goods in. Both are typed text for now (no master of provinces and
     * cities yet); a purchase picks the carrier in the supplier's city by default, matching the two cities as written.
     */
    public function up(): void
    {
        foreach (['suppliers', 'carriers'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('province', 100)->nullable()->after('address');
                $table->string('city', 100)->nullable()->after('province');
            });
        }
    }

    public function down(): void
    {
        foreach (['suppliers', 'carriers'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['province', 'city']);
            });
        }
    }
};

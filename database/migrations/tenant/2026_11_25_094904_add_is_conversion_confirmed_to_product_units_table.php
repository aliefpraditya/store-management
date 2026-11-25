<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A unit brought over from the old system keeps the conversion it had there, which is often just 1 because the old
     * system never filled it in. Such a unit is saved as not confirmed: it cannot be sold or counted in a stock opname
     * until a person has set its real conversion and confirmed it. Every unit that exists now is already trusted.
     */
    public function up(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->boolean('is_conversion_confirmed')->default(true)->after('is_sealed_pack');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->dropColumn('is_conversion_confirmed');
        });
    }
};

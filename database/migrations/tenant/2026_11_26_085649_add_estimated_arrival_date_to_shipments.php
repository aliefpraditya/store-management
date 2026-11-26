<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the carrier expects to reach the store, as typed from the packing list or the carrier. Optional: without it the
     * estimate is the departure date plus the carrier's usual transit days.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->date('estimated_arrival_date')->nullable()->after('departure_date');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('estimated_arrival_date');
        });
    }
};

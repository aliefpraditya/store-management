<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `client_uuid` makes a double submit of a delivery note give back the first one instead of making a second
     * (nullable only because notes made before this column existed have none). `business_date` is what the list
     * filters by date.
     */
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->unique()->after('id');
            $table->index('business_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropIndex(['business_date']);
            $table->dropUnique(['client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};

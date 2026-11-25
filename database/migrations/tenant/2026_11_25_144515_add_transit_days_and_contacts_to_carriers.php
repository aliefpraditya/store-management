<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `transit_days` is how long the carrier usually takes to bring goods to the store, kept so the arrival at the store can
     * be estimated once a container leaves. The people to call (customer service, drivers, a branch) live in their own
     * table since a carrier has several.
     */
    public function up(): void
    {
        Schema::table('carriers', function (Blueprint $table) {
            $table->unsignedSmallInteger('transit_days')->nullable()->after('address');
        });

        Schema::create('carrier_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_contacts');

        Schema::table('carriers', function (Blueprint $table) {
            $table->dropColumn('transit_days');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A carrier is the logistics company a purchase is sent to the store with. A purchase may name one (`carrier_id`), and
     * `purchases.expected_date` is the day the goods are expected to arrive at that carrier, not at the store.
     */
    public function up(): void
    {
        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('carrier_id')->nullable()->after('expected_date')->constrained();
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carrier_id');
        });

        Schema::dropIfExists('carriers');
    }
};

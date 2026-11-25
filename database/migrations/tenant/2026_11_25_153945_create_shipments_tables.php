<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A shipment is one trip of purchases with a carrier: it holds whole purchases (a purchase is in at most one shipment,
     * `purchases.shipment_id`) and the carrier's packing list, written down as lines. A line says how much of a purchase line
     * is in which container and what the carrier charged for it, so one purchase line can be split over several containers
     * (1000 bags, 100 to a container). Departing (`departure_date`) is what turns the purchases into shipped. `created_by` is a
     * central user id (no foreign key across databases).
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->string('number')->unique();
            $table->foreignId('carrier_id')->constrained();
            $table->string('vessel_name', 100)->nullable();
            $table->date('departure_date')->nullable();
            $table->enum('status', ['draft', 'shipped', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->string('created_by_name');
            $table->dateTime('shipped_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('shipment_id')->nullable()->after('carrier_id')->constrained();
        });

        Schema::create('shipment_containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained();
            $table->string('number', 50);
            $table->timestamps();

            $table->unique(['shipment_id', 'number']);
        });

        Schema::create('shipment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_container_id')->constrained();
            $table->foreignId('purchase_item_id')->constrained();
            $table->decimal('quantity', 15, 3);
            $table->decimal('base_quantity', 15, 3);
            $table->decimal('freight_cost', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['shipment_container_id', 'purchase_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_lines');
        Schema::dropIfExists('shipment_containers');

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipment_id');
        });

        Schema::dropIfExists('shipments');
    }
};

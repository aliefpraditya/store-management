<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `tax_number` is the supplier's NPWP, kept as text because it is written with dots and dashes. The people to call
     * live in their own table since one supplier can have several salespeople.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('email')->nullable()->after('address');
            $table->string('tax_number', 30)->nullable()->after('email');
        });

        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_contacts');

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['email', 'tax_number']);
        });
    }
};

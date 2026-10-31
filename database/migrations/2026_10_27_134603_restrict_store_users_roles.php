<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * For now there are only two store roles (Admin Toko and Kasir); `warehouse` is dropped.
     * The change fails on purpose if a row still uses it.
     */
    public function up(): void
    {
        Schema::table('store_users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'cashier'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'warehouse', 'cashier'])->change();
        });
    }
};

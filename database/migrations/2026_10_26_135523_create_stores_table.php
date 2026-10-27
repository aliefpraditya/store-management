<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('receipt_prefix', 10);
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('db_name')->unique();
            $table->string('db_username')->nullable();
            $table->text('db_password')->nullable();
            $table->string('db_host')->nullable();
            $table->enum('status', ['provisioning', 'active', 'inactive', 'failed'])->default('provisioning');
            $table->string('schema_version')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};

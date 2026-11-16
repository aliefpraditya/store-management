<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `document_sequences.register_id` is 0 for documents that are not numbered per register.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->string('type', 30);
            $table->unsignedBigInteger('register_id')->default(0);
            $table->string('period', 4);
            $table->unsignedInteger('last_number')->default(0);

            $table->primary(['type', 'register_id', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('settings');
    }
};

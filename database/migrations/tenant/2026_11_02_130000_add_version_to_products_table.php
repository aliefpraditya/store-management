<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Counts every save of a product with its units. The edit form carries the number it was opened with, and a save
     * with an older number is refused, so two people editing prices at the same time cannot silently overwrite each
     * other (a price change alone does not touch the product row, so `updated_at` would not show it).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};

<?php

use App\Models\ExpenseCategory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The usual expense categories used to be made only when a store was provisioned, so a store that already existed
     * had none and the till's expense window had nothing to choose from. Safe to run on a store that has them: a
     * category that is already there (even renamed to inactive) is left alone.
     */
    public function up(): void
    {
        foreach (ExpenseCategory::DEFAULT_NAMES as $categoryName) {
            ExpenseCategory::query()->firstOrCreate(['name' => $categoryName], ['is_active' => true]);
        }
    }

    /**
     * Reverse the migrations. Categories may already carry expenses, so nothing is removed.
     */
    public function down(): void {}
};

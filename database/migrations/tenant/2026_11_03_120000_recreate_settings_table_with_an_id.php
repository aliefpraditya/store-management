<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The first `settings` table was keyed by the setting's name, which the audit trail cannot point at (it records
     * the numeric id of the audited row). Nothing was ever stored in it, so it is simply made again with an id. A table
     * that already has an id is left alone, and one that does hold rows stops the migration rather than lose them:
     * dropping a table cannot be rolled back if the new one then fails to be made.
     */
    public function up(): void
    {
        if (Schema::hasColumn('settings', 'id')) {
            return;
        }

        if (Schema::hasTable('settings') && DB::table('settings')->exists()) {
            throw new RuntimeException('The settings table holds rows, and this migration would drop them. Copy them out and run it again.');
        }

        Schema::dropIfExists('settings');

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
        });
    }
};

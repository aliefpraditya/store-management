<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A unit gets a short, unique, upper-case code (PCS, DUS, KG) next to its name. Existing units start
     * from their name; if two names collapse to the same code, the id is appended to the later one.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('id');
        });

        $takenCodes = [];

        foreach (DB::table('units')->orderBy('id')->get(['id', 'name']) as $unit) {
            $code = Str::limit(Str::upper(preg_replace('/\s+/', ' ', trim($unit->name))), 20, '');

            if (in_array($code, $takenCodes, true)) {
                $code = Str::limit($code, 20 - strlen((string) $unit->id) - 1, '').'-'.$unit->id;
            }

            $takenCodes[] = $code;

            DB::table('units')->where('id', $unit->id)->update(['code' => $code]);
        }

        Schema::table('units', function (Blueprint $table) {
            $table->string('code', 20)->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};

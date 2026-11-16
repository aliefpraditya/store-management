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
     * A unit is identified by its code (PCS, DUS, KG) instead of a number, so `product_units.unit_code` and
     * `products.base_unit_code` read as what they are. The name (Pieces) stays a plain description. Renaming
     * a code is safe: the foreign keys update along with it.
     */
    public function up(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->string('unit_code', 20)->nullable()->after('product_id');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('base_unit_code', 20)->nullable()->after('brand_id');
        });

        DB::statement('update product_units join units on units.id = product_units.unit_id set product_units.unit_code = units.code');
        DB::statement('update products join units on units.id = products.base_unit_id set products.base_unit_code = units.code');

        Schema::table('product_units', function (Blueprint $table) {
            // The product foreign key was served by the composite unique index that is about to go.
            $table->index('product_id');
            $table->dropForeign(['unit_id']);
            $table->dropUnique(['product_id', 'unit_id']);
            $table->dropColumn('unit_id');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['base_unit_id']);
            $table->dropColumn('base_unit_id');
        });

        DB::statement('alter table units modify id bigint unsigned not null');
        DB::statement('alter table units drop primary key');
        DB::statement('alter table units drop column id');
        DB::statement('alter table units drop index units_code_unique');
        DB::statement('alter table units add primary key (code)');

        Schema::table('product_units', function (Blueprint $table) {
            $table->string('unit_code', 20)->nullable(false)->change();
            $table->foreign('unit_code')->references('code')->on('units')->cascadeOnUpdate();
            $table->unique(['product_id', 'unit_code']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('base_unit_code', 20)->nullable(false)->change();
            $table->foreign('base_unit_code')->references('code')->on('units')->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->dropForeign(['unit_code']);
            $table->dropUnique(['product_id', 'unit_code']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['base_unit_code']);
        });

        DB::statement('alter table units drop primary key');
        DB::statement('alter table units add id bigint unsigned not null auto_increment primary key first');
        DB::statement('alter table units add unique units_code_unique (code)');

        Schema::table('product_units', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable()->after('product_id');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('base_unit_id')->nullable()->after('brand_id');
        });

        DB::statement('update product_units join units on units.code = product_units.unit_code set product_units.unit_id = units.id');
        DB::statement('update products join units on units.code = products.base_unit_code set products.base_unit_id = units.id');

        Schema::table('product_units', function (Blueprint $table) {
            $table->dropColumn('unit_code');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('base_unit_code');
        });

        Schema::table('product_units', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable(false)->change();
            $table->foreign('unit_id')->references('id')->on('units');
            $table->unique(['product_id', 'unit_id']);
            $table->dropIndex(['product_id']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('base_unit_id')->nullable(false)->change();
            $table->foreign('base_unit_id')->references('id')->on('units');
        });
    }
};

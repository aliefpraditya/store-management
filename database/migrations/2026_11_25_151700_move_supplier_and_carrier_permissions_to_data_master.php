<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = ['suppliers.manage', 'carriers.manage'];

    /**
     * Suppliers and carriers are master data, not purchases, so their permissions are listed under "Data Master" on the roles
     * page, next to the sidebar group that holds their menus.
     */
    public function up(): void
    {
        DB::table('permissions')->whereIn('name', self::PERMISSIONS)->update(['group' => 'Data Master']);
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', self::PERMISSIONS)->update(['group' => 'Pembelian']);
    }
};

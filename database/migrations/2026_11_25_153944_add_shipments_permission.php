<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION = 'shipments.manage';

    /**
     * The shipments menu has a permission of its own. It goes to the Admin Toko only; the Super Admin holds every permission.
     */
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insert(['name' => self::PERMISSION, 'label' => 'Kelola pengiriman ekspedisi (buat, isi packing list, batalkan)', 'group' => 'Pembelian', 'created_at' => $now, 'updated_at' => $now]);

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');

        if ($adminRoleId === null) {
            return;
        }

        DB::table('role_permissions')->insert([
            'role_id' => $adminRoleId,
            'permission_id' => DB::table('permissions')->where('name', self::PERMISSION)->value('id'),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', self::PERMISSION)->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Each menu of the purchasing section has a permission of its own, so a role can be given the suppliers without the
     * purchases or the other way round. Both go to the Admin Toko.
     *
     * @var array<string, array{label: string, group: string}>
     */
    private const PERMISSIONS = [
        'suppliers.manage' => ['label' => 'Kelola supplier', 'group' => 'Pembelian'],
        'purchases.manage' => ['label' => 'Kelola nota pembelian (buat, ubah, konfirmasi, batalkan)', 'group' => 'Pembelian'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $name => $permission) {
            DB::table('permissions')->insert([...$permission, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');

        if ($adminRoleId === null) {
            return;
        }

        $permissionIds = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');

        DB::table('role_permissions')->insert($permissionIds->map(fn (int $permissionId): array => ['role_id' => $adminRoleId, 'permission_id' => $permissionId])->all());
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->delete();
    }
};

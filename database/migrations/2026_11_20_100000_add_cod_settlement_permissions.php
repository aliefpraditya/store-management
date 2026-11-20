<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Receiving the money of a COD sale and approving a difference in it are two permissions of their own, both given to
     * the Admin Toko. A store can hand the first to its cashiers by editing the Kasir role.
     *
     * @var array<string, array{label: string, group: string}>
     */
    private const PERMISSIONS = [
        'cod.receive' => ['label' => 'Terima uang COD (konfirmasi pelunasan)', 'group' => 'Penjualan'],
        'cod.approve' => ['label' => 'Setujui selisih penerimaan uang COD', 'group' => 'Penjualan'],
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

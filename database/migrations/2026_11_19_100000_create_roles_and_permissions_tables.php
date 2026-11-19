<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The permissions as they were when roles arrived; later permissions come with their own migration.
     *
     * @var array<string, array{label: string, group: string}>
     */
    private const PERMISSIONS = [
        'dashboard.view' => ['label' => 'Lihat dashboard toko', 'group' => 'Umum'],
        'cashier.use' => ['label' => 'Pakai layar kasir (jual, shift, struk, retur & pengeluaran kasir)', 'group' => 'Penjualan'],
        'products.manage' => ['label' => 'Kelola barang (daftar, ubah, harga massal, label)', 'group' => 'Barang & Stok'],
        'stock_opnames.count' => ['label' => 'Stok opname: buat dan hitung', 'group' => 'Barang & Stok'],
        'stock_opnames.post' => ['label' => 'Stok opname: review selisih dan posting', 'group' => 'Barang & Stok'],
        'stock_history.view' => ['label' => 'Lihat histori stok', 'group' => 'Barang & Stok'],
        'sales.view' => ['label' => 'Lihat riwayat nota semua kasir', 'group' => 'Penjualan'],
        'sales.void' => ['label' => 'Void nota', 'group' => 'Penjualan'],
        'delivery_notes.view' => ['label' => 'Lihat dan cetak surat jalan', 'group' => 'Penjualan'],
        'delivery_notes.manage' => ['label' => 'Buat, kirim, selesaikan, dan batalkan surat jalan', 'group' => 'Penjualan'],
        'returns.manage' => ['label' => 'Kelola retur (buat, setujui, tolak)', 'group' => 'Penjualan'],
        'shifts.review' => ['label' => 'Rekap shift: putuskan selisih kas', 'group' => 'Kas & Harian'],
        'expenses.manage' => ['label' => 'Kelola pengeluaran', 'group' => 'Kas & Harian'],
        'daily_closings.manage' => ['label' => 'Tutup hari dan buka kembali', 'group' => 'Kas & Harian'],
        'reports.view' => ['label' => 'Lihat laporan penjualan', 'group' => 'Laporan & Pengaturan'],
        'settings.manage' => ['label' => 'Ubah pengaturan toko dan satuan', 'group' => 'Laporan & Pengaturan'],
    ];

    /**
     * The roles every install starts with. The Petugas Gudang counts stock and prints delivery notes; posting what the
     * count finds stays with the Admin Toko (analisis-sistem, BPMN-5).
     *
     * @var array<string, array{label: string, permissions: list<string>}>
     */
    private const SYSTEM_ROLES = [
        'admin' => ['label' => 'Admin Toko', 'permissions' => [
            'dashboard.view', 'cashier.use', 'products.manage', 'stock_opnames.count', 'stock_opnames.post', 'stock_history.view',
            'sales.view', 'sales.void', 'delivery_notes.view', 'delivery_notes.manage', 'returns.manage', 'shifts.review',
            'expenses.manage', 'daily_closings.manage', 'reports.view', 'settings.manage',
        ]],
        'cashier' => ['label' => 'Kasir', 'permissions' => ['cashier.use']],
        'warehouse' => ['label' => 'Petugas Gudang', 'permissions' => ['stock_opnames.count', 'delivery_notes.view']],
    ];

    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('label');
            $table->string('group', 60);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('label', 100)->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();

            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('permission_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();

            $table->primary(['user_id', 'permission_id']);
        });

        $this->seedPermissionsAndRoles();
        $this->moveStoreUsersToRoleId();
    }

    public function down(): void
    {
        Schema::table('store_users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'cashier'])->nullable()->after('user_id');
        });

        DB::statement('UPDATE store_users JOIN roles ON roles.id = store_users.role_id SET store_users.role = roles.name');

        Schema::table('store_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::table('store_users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'cashier'])->nullable(false)->change();
        });

        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }

    private function seedPermissionsAndRoles(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $name => $permission) {
            DB::table('permissions')->insert([...$permission, 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'name');

        foreach (self::SYSTEM_ROLES as $name => $role) {
            $roleId = DB::table('roles')->insertGetId(['name' => $name, 'label' => $role['label'], 'is_system' => true, 'created_at' => $now, 'updated_at' => $now]);

            DB::table('role_permissions')->insert(
                array_map(fn (string $permission): array => ['role_id' => $roleId, 'permission_id' => $permissionIds[$permission]], $role['permissions']),
            );
        }
    }

    private function moveStoreUsersToRoleId(): void
    {
        Schema::table('store_users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('user_id')->constrained('roles')->restrictOnDelete();
        });

        DB::statement('UPDATE store_users JOIN roles ON roles.name = store_users.role SET store_users.role_id = roles.id');

        Schema::table('store_users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable(false)->change();
            $table->dropColumn('role');
        });
    }
};

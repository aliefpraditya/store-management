<?php

namespace Database\Seeders;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the central database for local development.
     *
     * The store database itself (store_mitra_utama) is created by hand (in hPanel on Hostinger);
     * tenant tables are created with `php artisan tenants:migrate`.
     */
    public function run(): void
    {
        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'username' => 'admin',
            'email' => 'admin@example.test',
        ]);
        $superAdmin->forceFill(['is_super_admin' => true])->save();

        $store = Store::query()->create([
            'code' => 'mitra_utama',
            'receipt_prefix' => 'MU',
            'name' => 'Mitra Utama',
            'db_name' => 'store_mitra_utama',
            'status' => StoreStatus::Active,
        ]);

        $storeAdmin = User::factory()->create([
            'name' => 'Admin Mitra Utama',
            'username' => 'admin.mu',
            'email' => 'admin.mu@example.test',
        ]);
        $store->users()->attach($storeAdmin->id, ['role' => 'admin']);
    }
}

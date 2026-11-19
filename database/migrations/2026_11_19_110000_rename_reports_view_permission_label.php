<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `reports.view` opens the stock report as well as the sales report, so its label no longer says "penjualan" alone.
     */
    public function up(): void
    {
        $this->rename('Lihat laporan (penjualan dan stok)');
    }

    public function down(): void
    {
        $this->rename('Lihat laporan penjualan');
    }

    private function rename(string $label): void
    {
        DB::table('permissions')->where('name', 'reports.view')->update(['label' => $label, 'updated_at' => now()]);
    }
};

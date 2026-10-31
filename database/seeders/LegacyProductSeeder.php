<?php

namespace Database\Seeders;

use App\Services\LegacyProductImporter;
use Illuminate\Database\Seeder;

/**
 * Brings the Mitra Utama items of the old database (`LEGACY_DB_DATABASE`, default mitra_utama_existing) into a
 * store, with their brands, categories, all units and prices. Stock is not imported. Anything that could not be
 * carried over safely is written to storage/app/legacy-products-review.csv for a person to decide. Safe to
 * run again. See {@see LegacyProductImporter} for how the old data is read.
 *
 * Run it for one store: php artisan tenants:seed --tenants=mitra_utama --class='Database\Seeders\LegacyProductSeeder'
 */
class LegacyProductSeeder extends Seeder
{
    private const REVIEW_FILE = 'app/legacy-products-review.csv';

    public function run(LegacyProductImporter $importer): void
    {
        $result = $importer->import();

        $this->writeReviewFile($result['review']);

        $this->command?->info("Satuan baru: {$result['new_units']}. Barang baru: {$result['created']} (satuan tambahan: {$result['extra_units']}), sudah ada: {$result['existing']}.");
        $this->command?->info('Perlu ditinjau: '.count($result['review']).' baris, lihat '.storage_path(self::REVIEW_FILE));
    }

    /**
     * @param  list<array{sku: string, name: string, subject: string, value: string, reason: string}>  $rows
     */
    private function writeReviewFile(array $rows): void
    {
        $file = fopen(storage_path(self::REVIEW_FILE), 'w');

        fputcsv($file, ['SKU', 'Nama', 'Yang ditinjau', 'Nilai lama', 'Alasan'], escape: '');

        foreach ($rows as $row) {
            fputcsv($file, array_values($row), escape: '');
        }

        fclose($file);
    }
}

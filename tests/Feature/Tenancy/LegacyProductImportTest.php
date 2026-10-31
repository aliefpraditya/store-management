<?php

namespace Tests\Feature\Tenancy;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Unit;
use App\Services\LegacyProductImporter;
use Database\Seeders\LegacyProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

/**
 * Runs the importer against a small copy of the old database layout (created for each test).
 */
class LegacyProductImportTest extends TestCase
{
    use PreparesStoreDatabase {
        tearDown as private tearDownStore;
    }
    use RefreshDatabase;

    private const STORE_DATABASE = 'store_test_legacy_import';

    private const LEGACY_DATABASE = 'legacy_test_catalog';

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_legacy_import']);
        $this->createLegacyDatabase();
    }

    protected function tearDown(): void
    {
        DB::purge(LegacyProductImporter::CONNECTION);
        DB::connection('mysql')->statement('DROP DATABASE IF EXISTS `'.self::LEGACY_DATABASE.'`');

        $this->tearDownStore();
    }

    private function createLegacyDatabase(): void
    {
        DB::connection('mysql')->statement('DROP DATABASE IF EXISTS `'.self::LEGACY_DATABASE.'`');
        DB::connection('mysql')->statement('CREATE DATABASE `'.self::LEGACY_DATABASE.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        config(['database.connections.'.LegacyProductImporter::CONNECTION.'.database' => self::LEGACY_DATABASE]);
        DB::purge(LegacyProductImporter::CONNECTION);

        $legacy = DB::connection(LegacyProductImporter::CONNECTION);

        $legacy->statement('CREATE TABLE satuans (id INT PRIMARY KEY, nama VARCHAR(45))');
        $legacy->statement('CREATE TABLE brands (id INT PRIMARY KEY, nama VARCHAR(45))');
        $legacy->statement('CREATE TABLE jenis (id INT PRIMARY KEY, nama VARCHAR(45))');
        $legacy->statement('CREATE TABLE barangs (id VARCHAR(45) PRIMARY KEY, nama VARCHAR(45), brands_id INT, jenis_id INT, owners_id INT, barcode VARCHAR(45), minimal_stok INT NULL, status_aktif VARCHAR(10))');
        $legacy->statement('CREATE TABLE konversi (barangs_id VARCHAR(45), satuans_id INT, konversi FLOAT, harga INT, ket VARCHAR(4), created_at DATETIME, deleted_at DATETIME NULL)');

        foreach ([1 => 'PCS', 2 => 'DUS', 3 => 'KG', 4 => 'SAK', 5 => 'LUSIN', 6 => 'METER', 7 => '50 METER', 8 => 'AMBIL SENDIRI', 9 => '3/1000'] as $id => $name) {
            $legacy->table('satuans')->insert(['id' => $id, 'nama' => $name]);
        }

        $legacy->table('brands')->insert([['id' => 1, 'nama' => 'Avian'], ['id' => 2, 'nama' => ' ']]);
        $legacy->table('jenis')->insert([['id' => 1, 'nama' => 'Cat'], ['id' => 2, 'nama' => 'Paku']]);

        $this->legacyItem('MU0001', 'CAT TEMBOK', 1, 1, '8991234567890', 5);
        $this->legacyItem('MU0002', 'PAKU 5CM', 2, 2, '0', null);
        $this->legacyItem('MU 2409', 'MCB IP C16', 1, 1, '0', 8);
        $this->legacyItem('MU2409', 'STATER ALKON', 1, 1, '5', 3, status: 'Non Aktif');
        $this->legacyItem('MU0003', 'SARUNG TANGAN', 1, 1, '8991234567890', 1);
        $this->legacyItem('MU0004', 'KABEL NYA', 1, 1, '0', 1);
        $this->legacyItem('MU0005', 'KUAS', 1, 1, '0', 1);
        $this->legacyItem('CB0001', 'CAT CAHAYA BARU', 1, 1, '0', 1, owner: 2);

        $this->legacyPrice('MU0001', 1, 'beli', 10000);
        $this->legacyPrice('MU0001', 1, 'jual', 15000);
        $this->legacyPrice('MU0002', 3, 'beli', 15200);
        $this->legacyPrice('MU0002', 3, 'jual', 23000);
        $this->legacyPrice('MU0002', 4, 'jual', 590000);
        $this->legacyPrice('MU0002', 2, 'jual', 500000, factor: 25);
        $this->legacyPrice('MU 2409', 1, 'beli', 1);
        $this->legacyPrice('MU 2409', 1, 'jual', 100000, deleted: true);
        $this->legacyPrice('MU 2409', 1, 'jual', 117000, createdAt: '2026-07-22 08:30:30');
        $this->legacyPrice('MU2409', 1, 'beli', 5000);
        $this->legacyPrice('MU2409', 1, 'jual', 8000);
        $this->legacyPrice('MU0003', 1, 'beli', 2400);
        $this->legacyPrice('MU0003', 1, 'jual', 5000);
        $this->legacyPrice('MU0003', 5, 'jual', 54000);
        $this->legacyPrice('MU0004', 6, 'beli', 9000);
        $this->legacyPrice('MU0004', 6, 'jual', 12000);
        $this->legacyPrice('MU0004', 7, 'jual', 565000);
        $this->legacyPrice('MU0005', 1, 'beli', 4000);
        $this->legacyPrice('MU0005', 1, 'jual', 6000, createdAt: '2021-08-16 00:00:00');
        $this->legacyPrice('MU0005', 1, 'jual', 7500, createdAt: '2026-01-05 00:00:00');
        $this->legacyPrice('CB0001', 1, 'beli', 1000);
        $this->legacyPrice('CB0001', 1, 'jual', 2000);
    }

    private function legacyItem(string $id, string $name, int $brandId, int $categoryId, string $barcode, ?int $minimumStock, int $owner = 1, string $status = 'Aktif'): void
    {
        DB::connection(LegacyProductImporter::CONNECTION)->table('barangs')->insert([
            'id' => $id, 'nama' => $name, 'brands_id' => $brandId, 'jenis_id' => $categoryId, 'owners_id' => $owner,
            'barcode' => $barcode, 'minimal_stok' => $minimumStock, 'status_aktif' => $status,
        ]);
    }

    private function legacyPrice(string $itemId, int $unitId, string $kind, int $price, float $factor = 1, bool $deleted = false, string $createdAt = '2021-08-16 00:00:00'): void
    {
        DB::connection(LegacyProductImporter::CONNECTION)->table('konversi')->insert([
            'barangs_id' => $itemId, 'satuans_id' => $unitId, 'konversi' => $factor, 'harga' => $price, 'ket' => $kind,
            'created_at' => $createdAt, 'deleted_at' => $deleted ? '2026-07-22 08:30:30' : null,
        ]);
    }

    /**
     * @return array{created: int, existing: int, extra_units: int, new_units: int, review: list<array<string, string>>}
     */
    private function import(): array
    {
        return $this->store->run(fn () => app(LegacyProductImporter::class)->import());
    }

    private function product(string $sku): Product
    {
        return $this->store->run(fn () => Product::query()->where('sku', $sku)->with(['category', 'brand', 'productUnits'])->firstOrFail());
    }

    public function test_a_selling_row_with_no_price_is_imported_as_a_zero_price_instead_of_stopping_the_import(): void
    {
        $this->legacyItem('MU0006', 'LEM KAYU', 1, 1, '0', 1);
        $this->legacyPrice('MU0006', 1, 'beli', 3000);
        $this->legacyPrice('MU0006', 1, 'jual', 5000);
        DB::connection(LegacyProductImporter::CONNECTION)->table('konversi')->insert([
            'barangs_id' => 'MU0006', 'satuans_id' => 5, 'konversi' => 1, 'harga' => null, 'ket' => 'jual', 'created_at' => '2021-08-16 00:00:00',
        ]);

        $this->assertSame(8, $this->import()['created']);

        $dozen = $this->product('MU0006')->productUnits->firstWhere('unit_code', 'LSN');
        $this->assertNotNull($dozen);
        $this->assertEquals(0, $dozen->selling_price);
        $this->assertEquals(36000, $dozen->cost_price);
    }

    public function test_only_mitra_utama_items_are_imported_with_their_category_and_brand(): void
    {
        $result = $this->import();

        $this->assertSame(7, $result['created']);
        $this->store->run(function (): void {
            $this->assertSame(7, Product::query()->count());
            $this->assertSame(0, Product::query()->where('sku', 'CB0001')->count());
            $this->assertSame(['Paku', 'Cat'], Category::query()->orderBy('id')->pluck('name')->sortDesc()->values()->all());
            $this->assertSame(['Avian'], Brand::query()->pluck('name')->all());
        });

        $paint = $this->product('MU0001');
        $this->assertSame(['CAT TEMBOK', 'Cat', 'Avian', '5.000', true], [$paint->name, $paint->category->name, $paint->brand?->name, $paint->min_stock, $paint->is_active]);
        $this->assertSame(['PCS', '10000.00', '15000.00', '8991234567890'], [$paint->productUnits[0]->unit_code, $paint->productUnits[0]->cost_price, $paint->productUnits[0]->selling_price, $paint->productUnits[0]->barcode]);
    }

    public function test_a_blank_brand_means_no_brand_and_a_missing_minimum_stock_means_zero(): void
    {
        $this->import();

        $nails = $this->product('MU0002');

        $this->assertNull($nails->brand_id);
        $this->assertSame('0.000', $nails->min_stock);
        $this->assertFalse($this->product('MU2409')->is_active);
    }

    public function test_every_old_unit_becomes_a_unit_of_the_store_even_when_no_item_uses_it(): void
    {
        $result = $this->import();

        $this->assertSame(9, $result['new_units']);
        $this->store->run(function (): void {
            $this->assertSame('Pieces', Unit::query()->find('PCS')->name);
            $this->assertSame('Meter', Unit::query()->find('MTR')->name);
            $this->assertSame('Lusin', Unit::query()->find('LSN')->name);
            $this->assertNotNull(Unit::query()->find('AMBIL SENDIRI'));
            $this->assertSame('3/1000', str_replace('-', '/', Unit::query()->find('3-1000')->code));
        });
    }

    public function test_the_unit_with_a_purchase_price_is_the_base_and_its_price_is_the_cost(): void
    {
        $this->import();

        $nails = $this->product('MU0002');

        $this->assertSame('KG', $nails->base_unit_code);
        $this->assertSame('15200.00', $nails->cost_price);
        $this->assertSame(['KG', 'DUS'], $nails->productUnits->pluck('unit_code')->all());
        $this->assertSame(['1.000', '25.000'], $nails->productUnits->pluck('conversion_factor')->all());
        $this->assertSame(['23000.00', '500000.00'], $nails->productUnits->pluck('selling_price')->all());
        $this->assertSame(['15200.00', '380000.00'], $nails->productUnits->pluck('cost_price')->all());
    }

    public function test_an_extra_unit_with_an_unknown_factor_is_left_out_and_reported(): void
    {
        $result = $this->import();

        $this->assertNotContains('SAK', $this->product('MU0002')->productUnits->pluck('unit_code')->all());

        $reported = collect($result['review'])->firstWhere('subject', 'Satuan SAK');
        $this->assertSame(['MU0002', '590000'], [$reported['sku'], $reported['value']]);
    }

    public function test_a_factor_of_one_is_believed_only_when_the_unit_name_says_so(): void
    {
        $this->import();

        $gloves = $this->product('MU0003');
        $this->assertSame(['PCS', 'LSN'], $gloves->productUnits->pluck('unit_code')->all());
        $this->assertSame(['1.000', '12.000'], $gloves->productUnits->pluck('conversion_factor')->all());

        $cable = $this->product('MU0004');
        $this->assertSame(['MTR', '50 METER'], $cable->productUnits->pluck('unit_code')->all());
        $this->assertSame(['1.000', '50.000'], $cable->productUnits->pluck('conversion_factor')->all());
        $this->assertSame(['12000.00', '565000.00'], $cable->productUnits->pluck('selling_price')->all());
    }

    public function test_sku_loses_its_spaces_and_keeps_a_dash_when_that_would_clash(): void
    {
        $this->import();

        $this->store->run(function (): void {
            $this->assertSame(['MU-2409', 'MU2409'], Product::query()->where('sku', 'like', 'MU%2409')->orderBy('sku')->pluck('sku')->all());
        });
        $this->assertSame('MCB IP C16', $this->product('MU-2409')->name);
    }

    public function test_soft_deleted_prices_are_ignored_and_the_newest_active_price_wins(): void
    {
        $this->import();

        $this->assertSame('117000.00', $this->product('MU-2409')->productUnits[0]->selling_price);
        $this->assertSame('7500.00', $this->product('MU0005')->productUnits[0]->selling_price);
    }

    public function test_a_cost_of_one_is_a_placeholder_and_becomes_zero(): void
    {
        $this->import();

        $this->assertSame('0.00', $this->product('MU-2409')->cost_price);
    }

    public function test_a_barcode_already_used_by_another_item_is_dropped_and_reported(): void
    {
        $result = $this->import();

        $this->assertSame('8991234567890', $this->product('MU0001')->productUnits[0]->barcode);
        $this->assertNull($this->product('MU0003')->productUnits[0]->barcode);
        $this->assertNull($this->product('MU2409')->productUnits[0]->barcode);

        $barcodeRows = collect($result['review'])->where('subject', 'Barcode')->pluck('sku')->sort()->values()->all();
        $this->assertSame(['MU0003', 'MU2409'], $barcodeRows);
    }

    public function test_no_stock_is_imported(): void
    {
        $this->import();

        $this->store->run(fn () => $this->assertSame(0, Stock::query()->count()));
    }

    public function test_running_it_again_adds_nothing(): void
    {
        $this->import();
        $second = $this->import();

        $this->assertSame([0, 7, 0], [$second['created'], $second['existing'], $second['new_units']]);
        $this->store->run(fn () => $this->assertSame(7, Product::query()->count()));
    }

    public function test_the_seeder_writes_what_needs_review_to_a_csv_file(): void
    {
        $path = storage_path('app/legacy-products-review.csv');
        @unlink($path);

        $this->store->run(fn () => app()->call([app(LegacyProductSeeder::class), 'run']));

        $this->assertFileExists($path);
        $this->assertStringContainsString('MU0002,"PAKU 5CM","Satuan SAK",590000', (string) file_get_contents($path));
        @unlink($path);
    }
}

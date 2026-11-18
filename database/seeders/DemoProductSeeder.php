<?php

namespace Database\Seeders;

use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameScope;
use App\Models\Product;
use App\Models\StockOpnameItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\ProductService;
use App\Services\StockOpnameService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sample items for trying out the cashier: each is sold in several units (loose and by the box, dozen,
 * roll...), and their stock comes in through a posted opening-balance opname
 * counted in mixed units, the same way a real store would enter it. Development data only; it is not part of
 * store provisioning. Safe to run again: products whose SKU exists are skipped.
 *
 * Run it for one store: php artisan tenants:seed --tenants=mitra_utama --class='Database\Seeders\DemoProductSeeder'
 *
 * @phpstan-type UnitSpec array{0: string, 1: int|float, 2: int|float, 3: int|float, 4?: bool}
 * @phpstan-type ProductSpec array{sku: string, name: string, category: string, brand: string|null, min_stock: int, is_active?: bool, units: list<UnitSpec>, stock: list<array{0: string, 1: int|float}>}
 */
class DemoProductSeeder extends Seeder
{
    /** @var array<string, string> unit code => name, for units a store may not have yet */
    private const UNITS = [
        'PCS' => 'Pieces', 'KG' => 'Kilogram', 'SAK' => 'Sak', 'PALET' => 'Palet', 'BTG' => 'Batang', 'IKAT' => 'Ikat',
        'MTR' => 'Meter', 'ROL' => 'Roll', 'LSN' => 'Lusin', 'LTR' => 'Liter', 'JRG' => 'Jerigen', 'PAIL' => 'Pail',
        'DUS' => 'Dus', 'BOX' => 'Box', 'PAK' => 'Pak', 'LBR' => 'Lembar',
    ];

    /**
     * Each unit is [code, conversion into the base unit, cost price, selling price, is it a sealed pack]; the first is the
     * base unit, and the last value is left out for units that are not a sealed pack (a roll that cannot be put back together
     * once cut). A stock entry is [unit code, quantity]; several entries for one product add up (5 DUS + 3 PAIL).
     *
     * @return list<ProductSpec>
     */
    private function products(): array
    {
        return [
            ['sku' => 'DEMO-01', 'name' => 'Paku 5cm', 'category' => 'Paku & Baut', 'brand' => null, 'min_stock' => 20,
                'units' => [['KG', 1, 17000, 22000], ['DUS', 25, 410000, 500000]], 'stock' => [['DUS', 3], ['KG', 12]]],
            ['sku' => 'DEMO-02', 'name' => 'Paku 7cm', 'category' => 'Paku & Baut', 'brand' => null, 'min_stock' => 20,
                'units' => [['KG', 1, 16500, 21000], ['DUS', 25, 400000, 485000]], 'stock' => [['KG', 60]]],
            ['sku' => 'DEMO-03', 'name' => 'Sekrup Gypsum 1"', 'category' => 'Paku & Baut', 'brand' => null, 'min_stock' => 500,
                'units' => [['PCS', 1, 250, 400], ['PAK', 100, 20000, 30000], ['DUS', 2000, 380000, 520000]], 'stock' => [['DUS', 1], ['PAK', 7], ['PCS', 40]]],
            ['sku' => 'DEMO-04', 'name' => 'Semen 40kg', 'category' => 'Bahan Bangunan', 'brand' => 'Tiga Roda', 'min_stock' => 20,
                'units' => [['SAK', 1, 58000, 65000], ['PALET', 40, 2250000, 2500000]], 'stock' => [['PALET', 2], ['SAK', 15]]],
            ['sku' => 'DEMO-05', 'name' => 'Pipa PVC 3"', 'category' => 'Pipa & Fitting', 'brand' => 'Rucika', 'min_stock' => 10,
                'units' => [['BTG', 1, 78000, 92000], ['IKAT', 10, 740000, 870000]], 'stock' => [['IKAT', 2], ['BTG', 6]]],
            ['sku' => 'DEMO-06', 'name' => 'Kabel NYM 2x1.5', 'category' => 'Listrik', 'brand' => 'Eterna', 'min_stock' => 50,
                'units' => [['MTR', 1, 9500, 12000], ['ROL', 50, 450000, 580000, true]], 'stock' => [['ROL', 4], ['MTR', 23]]],
            ['sku' => 'DEMO-07', 'name' => 'Selang Air 1/2"', 'category' => 'Pipa & Fitting', 'brand' => null, 'min_stock' => 50,
                'units' => [['MTR', 1, 5500, 7500], ['ROL', 100, 500000, 680000, true]], 'stock' => [['ROL', 2], ['MTR', 45]]],
            ['sku' => 'DEMO-08', 'name' => 'Lem Kayu Putih', 'category' => 'Lem & Perekat', 'brand' => null, 'min_stock' => 12,
                'units' => [['PCS', 1, 11000, 15000], ['LSN', 12, 125000, 165000], ['DUS', 144, 1380000, 1800000]], 'stock' => [['LSN', 3], ['PCS', 5]]],
            ['sku' => 'DEMO-09', 'name' => 'Kuas 3"', 'category' => 'Perkakas', 'brand' => null, 'min_stock' => 12,
                'units' => [['PCS', 1, 6500, 9000], ['LSN', 12, 72000, 100000]], 'stock' => [['LSN', 2], ['PCS', 7]]],
            ['sku' => 'DEMO-10', 'name' => 'Thinner', 'category' => 'Cat', 'brand' => null, 'min_stock' => 10,
                'units' => [['LTR', 1, 22000, 30000], ['JRG', 20, 400000, 540000]], 'stock' => [['JRG', 1], ['LTR', 12]]],
            ['sku' => 'DEMO-11', 'name' => 'Cat Tembok Putih 5kg', 'category' => 'Cat', 'brand' => 'Avian', 'min_stock' => 8,
                'units' => [['PAIL', 1, 105000, 125000], ['DUS', 4, 410000, 480000]], 'stock' => [['DUS', 5], ['PAIL', 3]]],
            ['sku' => 'DEMO-12', 'name' => 'Amplas No.120', 'category' => 'Perkakas', 'brand' => null, 'min_stock' => 20,
                'units' => [['LBR', 1, 1800, 3000], ['PAK', 10, 16000, 27000], ['BOX', 100, 150000, 230000]], 'stock' => [['BOX', 1], ['PAK', 4], ['LBR', 6]]],
            ['sku' => 'DEMO-13', 'name' => 'Bohlam LED 9W', 'category' => 'Listrik', 'brand' => 'Philips', 'min_stock' => 12,
                'units' => [['PCS', 1, 16000, 22000], ['DUS', 24, 360000, 480000]], 'stock' => [['DUS', 2], ['PCS', 10]]],
            ['sku' => 'DEMO-14', 'name' => 'Engsel Pintu 4"', 'category' => 'Perkakas', 'brand' => null, 'min_stock' => 6,
                'units' => [['PCS', 1, 9000, 13000], ['LSN', 12, 100000, 145000]], 'stock' => []],
            ['sku' => 'DEMO-15', 'name' => 'Gembok Lama (nonaktif)', 'category' => 'Perkakas', 'brand' => null, 'min_stock' => 0, 'is_active' => false,
                'units' => [['PCS', 1, 20000, 30000]], 'stock' => []],
            // 3 whole rolls plus two leftovers of 50 m: 400 m in all, but only 3 rolls can still be sold as a roll.
            ['sku' => 'DEMO-16', 'name' => 'Kawat Bendrat', 'category' => 'Bahan Bangunan', 'brand' => null, 'min_stock' => 100,
                'units' => [['MTR', 1, 1500, 2200], ['ROL', 100, 140000, 200000, true]], 'stock' => [['ROL', 3], ['MTR', 100]]],
        ];
    }

    public function run(ProductService $products, StockOpnameService $opnames): void
    {
        foreach (self::UNITS as $code => $name) {
            Unit::query()->firstOrCreate(['code' => $code], ['name' => $name]);
        }

        $createdSpecs = [];

        foreach ($this->products() as $spec) {
            $unitRows = [];

            foreach ($spec['units'] as $index => $unitSpec) {
                [$unitCode, $conversion, $cost, $price] = $unitSpec;
                $isSealedPack = $unitSpec[4] ?? false;

                $unitRows[] = [
                    'unit' => ['code' => $unitCode],
                    'conversion_factor' => $conversion,
                    'is_sellable' => true,
                    'cost_price' => $cost,
                    'selling_price' => $price,
                    'is_default_sale' => $index === 0,
                    'is_sealed_pack' => $isSealedPack,
                ];
            }

            if (Product::withTrashed()->where('sku', $spec['sku'])->exists()) {
                continue;
            }

            $products->create([
                'sku' => $spec['sku'],
                'name' => $spec['name'],
                'category' => ['name' => $spec['category']],
                'brand' => $spec['brand'] === null ? null : ['name' => $spec['brand']],
                'min_stock' => $spec['min_stock'],
                'is_active' => $spec['is_active'] ?? true,
                'units' => $unitRows,
            ]);

            $createdSpecs[] = $spec;
        }

        $this->enterOpeningStock($opnames, array_values(array_filter($createdSpecs, fn (array $spec): bool => $spec['stock'] !== [])));
    }

    /**
     * One opening-balance opname for the newly added products, counted in mixed units, then posted.
     *
     * @param  list<ProductSpec>  $specs
     */
    private function enterOpeningStock(StockOpnameService $opnames, array $specs): void
    {
        if ($specs === []) {
            return;
        }

        $userId = User::query()->where('is_super_admin', true)->value('id') ?? throw new \RuntimeException('Create a super admin first: the opname needs a user.');

        ['opname' => $opname] = $opnames->create([
            'warehouse_id' => DB::table('warehouses')->where('is_default', true)->value('id'),
            'opname_date' => now()->toDateString(),
            'scope' => StockOpnameScope::Selected->value,
            'reason_code' => StockOpnameReason::OpeningBalance->value,
        ], $userId);

        $productsBySku = Product::query()->whereIn('sku', array_column($specs, 'sku'))->get()->keyBy('sku');

        foreach ($specs as $spec) {
            $opnames->addProduct($opname, $productsBySku[$spec['sku']]);
        }

        $counts = [];

        foreach ($specs as $spec) {
            $counts[] = [
                'item_id' => StockOpnameItem::query()->where('stock_opname_id', $opname->id)->where('product_id', $productsBySku[$spec['sku']]->id)->value('id'),
                'parts' => array_map(fn (array $part): array => ['unit_code' => $part[0], 'quantity' => (string) $part[1]], $spec['stock']),
                'reason_id' => null,
                'note' => null,
            ];
        }

        $opnames->saveCounts($opname, $counts);
        $opnames->finishCounting($opname);
        $opnames->post($opname, $userId);
    }
}

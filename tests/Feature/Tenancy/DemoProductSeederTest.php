<?php

namespace Tests\Feature\Tenancy;

use App\Enums\StockMovementType;
use App\Enums\StockOpnameStatus;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class DemoProductSeederTest extends TestCase
{
    use PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_demo_seed';

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_demo_seed', 'receipt_prefix' => 'TDS']);
        User::factory()->create(['is_super_admin' => true]);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function inStore(callable $callback): mixed
    {
        return $this->store->run($callback);
    }

    private function runDemoSeeder(): void
    {
        $this->inStore(fn () => app()->call([app(DemoProductSeeder::class), 'run']));
    }

    private function stockOf(string $sku): string
    {
        return $this->inStore(fn () => (string) (Stock::query()->where('product_id', Product::query()->where('sku', $sku)->value('id'))->value('quantity') ?? '0.000'));
    }

    public function test_it_adds_items_sold_in_several_units_with_a_barcode_per_unit(): void
    {
        $this->runDemoSeeder();

        $this->inStore(function (): void {
            $this->assertSame(16, Product::query()->count());
            $this->assertSame(1, Product::query()->where('is_active', false)->count());

            $product = Product::query()->where('sku', 'DEMO-03')->with('productUnits')->firstOrFail();
            $this->assertSame(['PCS', 'PAK', 'DUS'], $product->productUnits->pluck('unit_code')->all());
            $this->assertSame(['1.000', '100.000', '2000.000'], $product->productUnits->pluck('conversion_factor')->all());
            $this->assertSame('PCS', $product->base_unit_code);

            $barcodes = ProductUnit::query()->whereNotNull('barcode')->pluck('barcode');
            $this->assertCount(ProductUnit::query()->count(), $barcodes);
            $this->assertSame($barcodes->count(), $barcodes->unique()->count());

            $this->assertSame(1, ProductUnit::query()->where('product_id', $product->id)->where('is_default_sale', true)->count());
        });
    }

    public function test_the_stock_arrives_through_one_posted_opening_balance_opname_in_the_base_unit(): void
    {
        $this->runDemoSeeder();

        $this->assertSame('87.000', $this->stockOf('DEMO-01'));
        $this->assertSame('2740.000', $this->stockOf('DEMO-03'));
        $this->assertSame('95.000', $this->stockOf('DEMO-04'));
        $this->assertSame('0.000', $this->stockOf('DEMO-14'));

        $this->inStore(function (): void {
            $opname = StockOpname::query()->sole();
            $this->assertSame(StockOpnameStatus::Posted, $opname->status);
            $this->assertSame(14, StockMovement::query()->count());
            $this->assertSame(14, StockMovement::query()->where('type', StockMovementType::OpeningBalance)->where('reference_id', $opname->id)->count());
        });
    }

    public function test_the_wire_is_sold_by_a_sealed_roll_and_starts_with_three_whole_rolls_of_its_400_m(): void
    {
        $this->runDemoSeeder();

        $this->inStore(function (): void {
            $wire = Product::query()->where('sku', 'DEMO-16')->with('productUnits')->firstOrFail();
            $this->assertSame('ROL', $wire->sealedPackUnit()->unit_code);

            $stock = Stock::query()->where('product_id', $wire->id)->sole();
            $this->assertSame(['400.000', 3], [$stock->quantity, $stock->intact_packs]);

            $this->assertSame(['ROL'], ProductUnit::query()->where('is_sealed_pack', true)->where('product_id', $wire->id)->pluck('unit_code')->all());
            $this->assertSame(3, ProductUnit::query()->where('is_sealed_pack', true)->count());
        });
    }

    public function test_running_it_again_adds_nothing(): void
    {
        $this->runDemoSeeder();
        $this->runDemoSeeder();

        $this->inStore(function (): void {
            $this->assertSame(16, Product::query()->count());
            $this->assertSame(1, StockOpname::query()->count());
            $this->assertSame(14, StockMovement::query()->count());
        });
    }
}

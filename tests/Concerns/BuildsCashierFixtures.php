<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Register;
use App\Models\Stock;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ShiftService;

/**
 * Shortcuts for tests of the cashier: users of a store, its default register, an open shift, and an item sold
 * by the piece or by the dozen. The class using it provides `$store` (see {@see PreparesStoreDatabase}).
 */
trait BuildsCashierFixtures
{
    protected function storeUser(UserRole $role = UserRole::Cashier): User
    {
        $user = User::factory()->create();
        $this->store->users()->attach($user->id, ['role' => $role->value]);

        return $user;
    }

    protected function register(): Register
    {
        return $this->store->run(fn () => Register::query()->where('code', 'K1')->firstOrFail());
    }

    protected function openShiftFor(User $user, ?Register $register = null): CashShift
    {
        $register ??= $this->register();

        return $this->store->run(fn () => app(ShiftService::class)->open($user, $register));
    }

    protected function mainWarehouse(): Warehouse
    {
        return $this->store->run(fn () => Warehouse::query()->where('code', 'MAIN')->firstOrFail());
    }

    /**
     * An item counted in PCS (cost 6.000, price 10.000) and sold by the DUS of 12 (cost 70.000, price 110.000).
     * The stock is in PCS in the main warehouse.
     *
     * @param  array<string, mixed>  $productAttributes
     */
    protected function sellableProduct(string $name = 'Kuas', string $stockInPieces = '100', array $productAttributes = []): Product
    {
        return $this->store->run(function () use ($name, $stockInPieces, $productAttributes): Product {
            $pcs = Unit::query()->firstOrCreate(['code' => 'PCS'], ['name' => 'Pieces']);
            $dus = Unit::query()->firstOrCreate(['code' => 'DUS'], ['name' => 'Dus']);

            $product = Product::factory()->create(['name' => $name, 'base_unit_code' => $pcs->code, 'cost_price' => 6000, ...$productAttributes]);

            ProductUnit::factory()->create([
                'product_id' => $product->id, 'unit_code' => $pcs->code, 'conversion_factor' => 1, 'is_base' => true,
                'cost_price' => 6000, 'selling_price' => 10000, 'sort_order' => 0,
            ]);
            ProductUnit::factory()->create([
                'product_id' => $product->id, 'unit_code' => $dus->code, 'conversion_factor' => 12, 'is_base' => false,
                'is_default_sale' => false, 'cost_price' => 70000, 'selling_price' => 110000, 'sort_order' => 1,
            ]);

            Stock::query()->updateOrInsert(
                ['product_id' => $product->id, 'warehouse_id' => $this->mainWarehouse()->id],
                ['quantity' => $stockInPieces],
            );

            return $product;
        });
    }

    protected function unitOf(Product $product, string $code): ProductUnit
    {
        return $this->store->run(fn () => ProductUnit::query()->where('product_id', $product->id)->where('unit_code', $code)->firstOrFail());
    }

    protected function stockInPieces(Product $product): string
    {
        return $this->store->run(fn () => (string) Stock::query()->where('product_id', $product->id)->where('warehouse_id', $this->mainWarehouse()->id)->value('quantity'));
    }
}

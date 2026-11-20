<?php

namespace Tests\Feature\Tenancy;

use App\Enums\ProductSortColumn;
use App\Enums\UserRole;
use App\Exceptions\ProductSaveException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use OwenIt\Auditing\Models\Audit;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_products';

    private Store $store;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_products']);
        $this->admin = $this->userWithRole(UserRole::StoreAdmin);
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $this->store->users()->attach($user->id, ['role_id' => Role::idOf($role)]);

        return $user;
    }

    private function url(string $route, array $parameters = []): string
    {
        return route("tenant.products.{$route}", ['tenant' => $this->store->code, ...$parameters]);
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Cat Tembok Putih 5kg',
            'sku' => 'MU0001',
            'category' => ['id' => null, 'name' => 'Cat'],
            'brand' => ['id' => null, 'name' => 'Dulux'],
            'min_stock' => 8,
            'is_active' => true,
            'units' => [
                $this->unitRow('PAIL', 1, '116600', isDefault: true, cost: '72875'),
                $this->unitRow('DUS', 4, '437250', cost: '280000'),
            ],
            ...$overrides,
        ];
    }

    /**
     * The form of an existing item as the browser sends it back: the save counter it was opened with comes along.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function editPayload(Product $product, array $overrides = []): array
    {
        return $this->payload(['version' => $this->inStore(fn () => Product::query()->whereKey($product->id)->value('version')), ...$overrides]);
    }

    private function unitRow(string $code, float|int|string $conversion, string $price, bool $isDefault = false, bool $isSellable = true, ?int $id = null, string $cost = '1000'): array
    {
        return [
            'id' => $id,
            'unit' => ['code' => $code],
            'conversion_factor' => $conversion,
            'is_sellable' => $isSellable,
            'cost_price' => $cost,
            'selling_price' => $price,
            'is_default_sale' => $isDefault,
        ];
    }

    /**
     * Units are added in the unit settings before an item uses them, so tests create the ones a payload names.
     *
     * @param  array<string, mixed>  $payload
     */
    private function ensureUnitsExist(array $payload): void
    {
        $this->inStore(function () use ($payload): void {
            foreach ($payload['units'] ?? [] as $row) {
                $code = Unit::normalizeCode((string) ($row['unit']['code'] ?? ''));

                if ($code !== '') {
                    Unit::query()->firstOrCreate(['code' => $code], ['name' => $code]);
                }
            }
        });
    }

    private function create(array $overrides = []): TestResponse
    {
        $payload = $this->payload($overrides);
        $this->ensureUnitsExist($payload);

        return $this->actingAs($this->admin)->post($this->url('store'), $payload);
    }

    private function savedProduct(string $sku = 'MU0001'): Product
    {
        return $this->inStore(fn () => Product::query()->with(['category', 'brand', 'baseUnit', 'productUnits.unit'])->where('sku', $sku)->firstOrFail());
    }

    public function test_only_admins_may_manage_items(): void
    {
        $cashier = $this->userWithRole(UserRole::Cashier);
        $product = $this->inStore(fn () => Product::factory()->create());

        $this->actingAs($cashier);
        $this->get($this->url('index'))->assertForbidden();
        $this->get($this->url('create'))->assertForbidden();
        $this->post($this->url('store'), $this->payload())->assertForbidden();
        $this->get($this->url('edit', ['product' => $product->id]))->assertForbidden();
        $this->put($this->url('update', ['product' => $product->id]), $this->payload())->assertForbidden();
        $this->delete($this->url('destroy', ['product' => $product->id]))->assertForbidden();

        $this->assertSame(1, $this->inStore(fn () => Product::query()->count()));
    }

    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get($this->url('index'))->assertRedirect(route('login'));
    }

    public function test_super_admin_may_open_the_list(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true]))
            ->get($this->url('index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Tenant/Products/Index'));
    }

    public function test_item_is_created_with_its_category_brand_and_units(): void
    {
        $this->create()->assertRedirect($this->url('index'))->assertSessionHas('success');

        $product = $this->savedProduct();

        $this->assertSame('Cat Tembok Putih 5kg', $product->name);
        $this->assertSame('Cat', $product->category->name);
        $this->assertSame('Dulux', $product->brand->name);
        $this->assertSame('PAIL', $product->baseUnit->name);
        $this->assertSame('PAIL', $product->baseUnit->code);
        $this->assertSame('72875.00', $product->cost_price);
        $this->assertSame('8.000', $product->min_stock);
        $this->assertTrue($product->is_active);

        [$base, $box] = $product->productUnits->all();

        $this->assertTrue($base->is_base);
        $this->assertSame('1.000', $base->conversion_factor);
        $this->assertSame('116600.00', $base->selling_price);
        $this->assertSame('72875.00', $base->cost_price);
        $this->assertTrue($base->is_default_sale);
        $this->assertFalse($box->is_base);
        $this->assertSame('DUS', $box->unit->name);
        $this->assertSame('4.000', $box->conversion_factor);
        $this->assertSame('280000.00', $box->cost_price);
        $this->assertFalse($box->is_default_sale);
        $this->assertSame([0, 1], [$base->sort_order, $box->sort_order]);
    }

    public function test_the_base_unit_always_has_conversion_one(): void
    {
        $this->create(['units' => [$this->unitRow('PAIL', 5, '1000', isDefault: true)]]);

        $this->assertSame('1.000', $this->savedProduct()->productUnits->first()->conversion_factor);
    }

    public function test_existing_category_brand_and_unit_are_reused_not_duplicated(): void
    {
        [$category, $brand, $unit] = $this->inStore(fn () => [
            Category::factory()->create(['name' => 'Cat']),
            Brand::factory()->create(['name' => 'Dulux']),
            Unit::factory()->create(['code' => 'PAIL', 'name' => 'PAIL']),
        ]);

        $this->create([
            'category' => ['id' => null, 'name' => 'cat'],
            'brand' => ['id' => $brand->id, 'name' => null],
            'units' => [$this->unitRow('pail', 1, '1000', isDefault: true)],
        ]);

        $product = $this->savedProduct();

        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($unit->code, $product->base_unit_code);
        $this->assertSame([1, 1, 1], $this->inStore(fn () => [Category::query()->count(), Brand::query()->count(), Unit::query()->count()]));
    }

    public function test_brand_is_optional(): void
    {
        $this->create(['brand' => null])->assertSessionHasNoErrors();

        $this->assertNull($this->savedProduct()->brand_id);
    }

    public function test_required_fields_are_checked(): void
    {
        $this->actingAs($this->admin)
            ->post($this->url('store'), [])
            ->assertSessionHasErrors(['name', 'sku', 'category', 'min_stock', 'is_active', 'units']);

        $this->assertSame(0, $this->inStore(fn () => Product::query()->count()));
    }

    public function test_sku_must_be_unique_but_may_stay_on_the_same_item(): void
    {
        $this->create()->assertSessionHasNoErrors();

        $this->create(['units' => [$this->unitRow('PCS', 1, '1000', isDefault: true)]])
            ->assertSessionHasErrors(['sku' => 'SKU sudah dipakai.']);

        $product = $this->savedProduct();

        $this->actingAs($this->admin)
            ->put($this->url('update', ['product' => $product->id]), $this->editPayload($product, [
                'units' => [
                    $this->unitRow('PAIL', 1, '116600', isDefault: true, id: $product->productUnits[0]->id),
                    $this->unitRow('DUS', 4, '437250', id: $product->productUnits[1]->id),
                ],
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_category_and_unit_must_be_picked_or_typed(): void
    {
        $this->create(['category' => ['id' => null, 'name' => null]])->assertSessionHasErrors('category.name');

        $this->create(['units' => [$this->unitRow('', 1, '1000', isDefault: true)]])->assertSessionHasErrors('units.0.unit.code');
    }

    public function test_conversion_must_be_positive_with_at_most_three_decimals(): void
    {
        foreach (['0', '-2', '1.2345'] as $conversion) {
            $this->create(['units' => [
                $this->unitRow('PAIL', 1, '1000', isDefault: true),
                $this->unitRow('DUS', $conversion, '4000'),
            ]])->assertSessionHasErrors('units.1.conversion_factor');
        }

        $this->create(['units' => [
            $this->unitRow('PAIL', 1, '1000', isDefault: true),
            $this->unitRow('DUS', '4.125', '4000'),
        ]])->assertSessionHasNoErrors();
    }

    public function test_prices_cannot_be_negative_or_have_more_than_two_decimals(): void
    {
        $this->create(['units' => [$this->unitRow('PAIL', 1, '1000', isDefault: true, cost: '-1')]])->assertSessionHasErrors('units.0.cost_price');
        $this->create(['units' => [$this->unitRow('PAIL', 1, '1000', isDefault: true, cost: '10.555')]])->assertSessionHasErrors('units.0.cost_price');
        $this->create(['units' => [$this->unitRow('PAIL', 1, '-5', isDefault: true)]])->assertSessionHasErrors('units.0.selling_price');
        $this->create(['units' => [$this->unitRow('PAIL', 1, '10.555', isDefault: true)]])->assertSessionHasErrors('units.0.selling_price');
    }

    public function test_a_price_below_the_cost_is_still_accepted(): void
    {
        $this->create(['units' => [$this->unitRow('PAIL', 1, '1000', isDefault: true, cost: '5000')]])
            ->assertSessionHasNoErrors();
    }

    public function test_one_unit_must_be_sellable_and_exactly_one_must_be_the_default(): void
    {
        $this->create(['units' => [
            $this->unitRow('PAIL', 1, '1000', isDefault: true, isSellable: false),
            $this->unitRow('DUS', 4, '4000', isSellable: false),
        ]])->assertSessionHasErrors('units');

        $this->create(['units' => [
            $this->unitRow('PAIL', 1, '1000'),
            $this->unitRow('DUS', 4, '4000'),
        ]])->assertSessionHasErrors(['units' => 'Pilih tepat satu satuan default untuk kasir.']);

        $this->create(['units' => [
            $this->unitRow('PAIL', 1, '1000', isDefault: true),
            $this->unitRow('DUS', 4, '4000', isDefault: true),
        ]])->assertSessionHasErrors(['units' => 'Pilih tepat satu satuan default untuk kasir.']);

        $this->create(['units' => [
            $this->unitRow('PAIL', 1, '1000', isSellable: false),
            $this->unitRow('DUS', 4, '4000', isDefault: true),
        ]])->assertSessionHasNoErrors();
    }

    public function test_default_unit_must_be_sellable(): void
    {
        $this->create(['units' => [
            $this->unitRow('PAIL', 1, '1000', isDefault: true, isSellable: false),
            $this->unitRow('DUS', 4, '4000'),
        ]])->assertSessionHasErrors(['units' => 'Satuan default kasir harus bisa dijual.']);
    }

    public function test_a_unit_cannot_be_listed_twice_even_by_a_typed_name(): void
    {
        $existing = $this->inStore(fn () => Unit::factory()->create(['code' => 'PAIL', 'name' => 'PAIL']));

        $this->create(['units' => [
            $this->unitRow('PAIL', 1, '1000', isDefault: true),
            $this->unitRow('pail', 4, '4000'),
        ]])->assertSessionHasErrors('units.1.unit.code');
    }

    public function test_a_typed_code_finds_the_existing_unit_whatever_its_letter_case(): void
    {
        $this->inStore(fn () => Unit::factory()->create(['code' => 'DUS', 'name' => 'Karton besar']));

        $this->create(['units' => [$this->unitRow('dus', 1, '1000', isDefault: true)]])->assertSessionHasNoErrors();

        $unit = $this->savedProduct()->baseUnit;

        $this->assertSame(['DUS', 'Karton besar'], [$unit->code, $unit->name]);
        $this->assertSame(1, $this->inStore(fn () => Unit::query()->count()));
    }

    public function test_a_unit_that_does_not_exist_is_rejected_and_not_created(): void
    {
        $this->actingAs($this->admin)
            ->post($this->url('store'), $this->payload(['units' => [$this->unitRow('XYZ', 1, '1000', isDefault: true)]]))
            ->assertSessionHasErrors('units.0.unit.code');

        $this->assertSame(0, $this->inStore(fn () => Unit::query()->count()));
        $this->assertDatabaseMissing('products', ['sku' => 'MU0001']);
    }

    public function test_the_form_offers_units_with_their_code_and_name(): void
    {
        $this->inStore(fn () => Unit::factory()->create(['code' => 'DUS', 'name' => 'Karton besar']));

        $this->actingAs($this->admin)->get($this->url('create'))->assertInertia(fn (Assert $page) => $page
            ->where('units.0.code', 'DUS')
            ->where('units.0.name', 'Karton besar')
            ->missing('units.0.id')
        );
    }

    public function test_a_packaging_barcode_is_saved_and_left_empty_when_there_is_none(): void
    {
        $this->create(['barcode' => ' 8993175530012 '])->assertSessionHasNoErrors();
        $this->assertSame('8993175530012', $this->savedProduct()->barcode);

        $this->create(['sku' => 'MU0002', 'barcode' => ''])->assertSessionHasNoErrors();
        $this->assertNull($this->savedProduct('MU0002')->barcode);
    }

    public function test_two_items_without_a_barcode_do_not_clash(): void
    {
        $this->create()->assertSessionHasNoErrors();
        $this->create(['sku' => 'MU0002'])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->inStore(fn () => Product::query()->whereNull('barcode')->count()));
    }

    public function test_a_barcode_cannot_be_used_by_two_items_but_may_stay_on_its_own_item(): void
    {
        $this->create(['barcode' => '8993175530012'])->assertSessionHasNoErrors();

        $this->create(['sku' => 'MU0002', 'barcode' => '8993175530012'])->assertSessionHasErrors('barcode');
        $this->assertDatabaseMissing('products', ['sku' => 'MU0002']);

        $product = $this->savedProduct();
        $this->ensureUnitsExist($this->payload());
        $this->actingAs($this->admin)
            ->put($this->url('update', ['product' => $product->id]), $this->editPayload($product, ['barcode' => '8993175530012', 'name' => 'Cat Putih']))
            ->assertSessionHasNoErrors();
    }

    public function test_a_barcode_cannot_be_the_sku_of_another_item_and_the_other_way_round(): void
    {
        $this->create(['sku' => 'MU0001', 'barcode' => '8993175530012'])->assertSessionHasNoErrors();

        $this->create(['sku' => 'MU0002', 'barcode' => 'MU0001'])->assertSessionHasErrors('barcode');
        $this->create(['sku' => '8993175530012'])->assertSessionHasErrors('sku');
        $this->assertSame(1, $this->inStore(fn () => Product::query()->count()));
    }

    public function test_the_edit_form_carries_the_barcode_and_the_list_finds_an_item_by_it(): void
    {
        $this->create(['barcode' => '8993175530012']);
        $product = $this->savedProduct();

        $this->actingAs($this->admin)->get($this->url('edit', ['product' => $product->id]))->assertInertia(fn (Assert $page) => $page
            ->where('product.barcode', '8993175530012')
        );

        $this->actingAs($this->admin)->get($this->url('index', ['search' => '899317553']))->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $product->id)
        );
    }

    public function test_the_add_form_starts_with_the_first_sku_of_the_store_prefix(): void
    {
        $this->actingAs($this->admin)->get($this->url('create'))->assertInertia(fn (Assert $page) => $page
            ->where('suggested_sku', $this->store->receipt_prefix.'0001')
        );
    }

    public function test_the_suggested_sku_follows_the_highest_number_of_the_prefix_including_deleted_items(): void
    {
        $prefix = $this->store->receipt_prefix;

        $this->inStore(function () use ($prefix): void {
            Product::factory()->create(['sku' => "{$prefix}0007"]);
            Product::factory()->create(['sku' => "{$prefix}3470"]);
            Product::factory()->create(['sku' => "{$prefix}3500"])->delete();
            Product::factory()->create(['sku' => 'OTHER9999']);
            Product::factory()->create(['sku' => "{$prefix}-12"]);
        });

        $this->actingAs($this->admin)->get($this->url('create'))->assertInertia(fn (Assert $page) => $page
            ->where('suggested_sku', "{$prefix}3501")
        );
    }

    public function test_the_edit_form_does_not_suggest_a_sku(): void
    {
        $product = $this->inStore(fn () => Product::factory()->create());

        $this->actingAs($this->admin)->get($this->url('edit', ['product' => $product->id]))->assertInertia(fn (Assert $page) => $page
            ->missing('suggested_sku')
        );
    }

    public function test_every_unit_may_have_decimal_quantities_up_to_three_decimals(): void
    {
        $this->create(['min_stock' => '2.5', 'units' => [$this->unitRow('PCS', 1, '1000', isDefault: true)]])
            ->assertSessionHasNoErrors();

        $this->assertSame('2.500', $this->savedProduct()->min_stock);

        $this->create(['sku' => 'MU0002', 'min_stock' => '1.2345', 'units' => [$this->unitRow('KG', 1, '1000', isDefault: true)]])
            ->assertSessionHasErrors('min_stock');

        $this->assertDatabaseMissing('products', ['sku' => 'MU0002']);
    }

    public function test_edit_form_is_filled_with_the_item(): void
    {
        $this->create();
        $product = $this->savedProduct();

        $this->actingAs($this->admin)
            ->get($this->url('edit', ['product' => $product->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tenant/Products/Form')
                ->where('product.sku', 'MU0001')
                ->where('product.category.name', 'Cat')
                ->where('product.brand.name', 'Dulux')
                ->where('product.is_locked', false)
                ->where('product.units.0.unit.name', 'PAIL')
                ->where('product.units.0.unit.code', 'PAIL')
                ->where('product.units.0.cost_price', '72875.00')
                ->where('product.units.1.cost_price', '280000.00')
                ->where('product.units.1.conversion_factor', '4.000')
                ->where('form_action.method', 'put')
                ->has('categories', 1)
                ->has('units', 2)
            );
    }

    public function test_an_item_can_be_edited_and_units_added_removed_and_repriced(): void
    {
        $this->create();
        $product = $this->savedProduct();
        [$base, $box] = $product->productUnits->all();
        $this->ensureUnitsExist(['units' => [['unit' => ['code' => 'LUSIN']]]]);

        $this->actingAs($this->admin)->put($this->url('update', ['product' => $product->id]), $this->editPayload($product, [
            'name' => 'Cat Tembok Putih 25kg',
            'brand' => null,
            'is_active' => false,
            'units' => [
                $this->unitRow('PAIL', 1, '120000', isDefault: true, id: $base->id),
                $this->unitRow('LUSIN', 12, '1400000'),
            ],
        ]))->assertRedirect($this->url('index'));

        $product = $this->savedProduct();

        $this->assertSame('Cat Tembok Putih 25kg', $product->name);
        $this->assertNull($product->brand_id);
        $this->assertFalse($product->is_active);
        $this->assertSame(['PAIL', 'LUSIN'], $product->productUnits->map(fn ($productUnit) => $productUnit->unit->name)->all());
        $this->assertSame('120000.00', $product->productUnits[0]->selling_price);
        $this->assertSame($base->id, $product->productUnits[0]->id);
        $this->assertFalse($this->inStore(fn () => ProductUnit::query()->whereKey($box->id)->exists()));
    }

    public function test_unknown_unit_rows_cannot_be_edited(): void
    {
        $this->create();
        $other = $this->inStore(fn () => ProductUnit::factory()->create());
        $product = $this->savedProduct();

        $this->actingAs($this->admin)
            ->put($this->url('update', ['product' => $product->id]), $this->editPayload($product, [
                'units' => [$this->unitRow('PAIL', 1, '1000', isDefault: true, id: $other->id)],
            ]))
            ->assertSessionHasErrors('units.0.id');
    }

    /**
     * @return array{0: Product, 1: ProductUnit, 2: ProductUnit}
     */
    private function itemWithStock(): array
    {
        $this->create();
        $product = $this->savedProduct();

        $this->inStore(function () use ($product): void {
            $warehouse = Warehouse::query()->where('code', 'MAIN')->firstOrFail();
            Stock::factory()->create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 12]);
        });

        return [$product, $product->productUnits[0], $product->productUnits[1]];
    }

    public function test_an_item_with_stock_is_locked_in_the_form(): void
    {
        [$product] = $this->itemWithStock();

        $this->actingAs($this->admin)
            ->get($this->url('edit', ['product' => $product->id]))
            ->assertInertia(fn (Assert $page) => $page->where('product.is_locked', true));
    }

    public function test_a_locked_item_keeps_its_units_and_conversions(): void
    {
        [$product, $base, $box] = $this->itemWithStock();

        $update = fn (array $units) => $this->actingAs($this->admin)
            ->put($this->url('update', ['product' => $product->id]), $this->editPayload($product, ['units' => $units]));

        $unchanged = fn () => [
            $this->unitRow('PAIL', 1, '116600', isDefault: true, id: $base->id),
            $this->unitRow('DUS', 4, '437250', id: $box->id),
        ];

        $update([$unchanged()[0]])->assertSessionHasErrors(['units' => 'Satuan yang sudah dipakai tidak bisa dihapus.']);

        $changedConversion = $unchanged();
        $changedConversion[1]['conversion_factor'] = 6;
        $update($changedConversion)->assertSessionHasErrors('units.1.conversion_factor');

        $this->ensureUnitsExist(['units' => [['unit' => ['code' => 'KARTON']]]]);
        $changedUnit = $unchanged();
        $changedUnit[1]['unit'] = ['code' => 'KARTON'];
        $update($changedUnit)->assertSessionHasErrors('units.1.conversion_factor');

        $swappedBase = [$unchanged()[1], $unchanged()[0]];
        $swappedBase[0]['is_default_sale'] = true;
        $swappedBase[1]['is_default_sale'] = false;
        $update($swappedBase)->assertSessionHasErrors('units.0.unit.code');

        $this->assertSame('4.000', $this->savedProduct()->productUnits[1]->conversion_factor);
    }

    public function test_a_locked_item_still_allows_price_and_new_unit_changes(): void
    {
        [$product, $base, $box] = $this->itemWithStock();
        $this->ensureUnitsExist(['units' => [['unit' => ['code' => 'LUSIN']]]]);

        $this->actingAs($this->admin)->put($this->url('update', ['product' => $product->id]), $this->editPayload($product, [
            'units' => [
                $this->unitRow('PAIL', 1, '130000', isDefault: true, id: $base->id),
                $this->unitRow('DUS', 4, '437250', isSellable: false, id: $box->id),
                $this->unitRow('LUSIN', 12, '1400000'),
            ],
        ]))->assertSessionHasNoErrors();

        $product = $this->savedProduct();

        $this->assertSame('130000.00', $product->productUnits[0]->selling_price);
        $this->assertFalse($product->productUnits[1]->is_sellable);
        $this->assertCount(3, $product->productUnits);
    }

    /**
     * @param  list<array{name: string, sku: string, category?: string, active?: bool}>  $items
     */
    private function seedItems(array $items): void
    {
        foreach ($items as $item) {
            $this->create([
                'name' => $item['name'],
                'sku' => $item['sku'],
                'category' => ['id' => null, 'name' => $item['category'] ?? 'Umum'],
                'brand' => null,
                'is_active' => $item['active'] ?? true,
                'units' => [$this->unitRow('PCS', 1, '1000', isDefault: true)],
            ])->assertSessionHasNoErrors();
        }
    }

    public function test_the_list_can_be_searched_by_name_or_sku(): void
    {
        $this->seedItems([
            ['name' => 'Paku 5cm', 'sku' => 'MU0002'],
            ['name' => 'Lampu LED 9W', 'sku' => 'MU0003'],
        ]);

        $search = fn (string $term) => $this->actingAs($this->admin)->get($this->url('index', ['search' => $term]));

        foreach (['paku', 'MU0002'] as $term) {
            $search($term)->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.sku', 'MU0002')
                ->where('filters.search', $term)
            );
        }

        $search('tidak ada')->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
    }

    public function test_the_list_can_be_filtered_by_category_and_status(): void
    {
        $this->seedItems([
            ['name' => 'Paku', 'sku' => 'A1', 'category' => 'Bangunan'],
            ['name' => 'Cat', 'sku' => 'A2', 'category' => 'Cat'],
            ['name' => 'Kuas', 'sku' => 'A3', 'category' => 'Cat', 'active' => false],
        ]);

        $categoryId = $this->inStore(fn () => Category::query()->where('name', 'Cat')->value('id'));
        $get = fn (array $filters) => $this->actingAs($this->admin)->get($this->url('index', $filters));

        $get(['category' => $categoryId])->assertInertia(fn (Assert $page) => $page->has('products.data', 2));
        $get(['category' => $categoryId, 'status' => 'active'])->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.sku', 'A2'));
        $get(['status' => 'inactive'])->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.sku', 'A3'));
        $get([])->assertInertia(fn (Assert $page) => $page->has('products.data', 3)->has('categories', 2));
    }

    public function test_the_list_can_be_ordered_by_a_column_in_either_direction(): void
    {
        $this->seedItems([
            ['name' => 'Cat', 'sku' => 'A2', 'category' => 'Bangunan'],
            ['name' => 'Paku', 'sku' => 'A3', 'category' => 'Cat', 'active' => false],
            ['name' => 'Kuas', 'sku' => 'A1', 'category' => 'Alat'],
        ]);

        $skusOrderedBy = function (array $query): array {
            $skus = [];

            $this->actingAs($this->admin)->get($this->url('index', $query))->assertOk()
                ->assertInertia(function (Assert $page) use (&$skus): void {
                    $skus = collect($page->toArray()['props']['products']['data'])->pluck('sku')->all();
                });

            return $skus;
        };

        $this->assertSame(['A2', 'A1', 'A3'], $skusOrderedBy([]), 'Without a choice the list runs by name.');
        $this->assertSame(['A1', 'A2', 'A3'], $skusOrderedBy(['sort' => 'sku']));
        $this->assertSame(['A3', 'A2', 'A1'], $skusOrderedBy(['sort' => 'sku', 'direction' => 'desc']));
        $this->assertSame(['A1', 'A2', 'A3'], $skusOrderedBy(['sort' => 'category']));
        $this->assertSame(['A3', 'A2', 'A1'], $skusOrderedBy(['sort' => 'category', 'direction' => 'desc']));
        $this->assertSame('A3', $skusOrderedBy(['sort' => 'status', 'direction' => 'asc'])[0], 'Inactive items come first when ascending.');
        $this->assertSame(['A2', 'A1', 'A3'], $skusOrderedBy(['sort' => 'unknown', 'direction' => 'sideways']), 'An unknown column or direction falls back to the default.');
    }

    public function test_every_column_of_the_list_can_be_ordered_by(): void
    {
        $this->seedItems([
            ['name' => 'Paku', 'sku' => 'A1'],
            ['name' => 'Cat', 'sku' => 'A2'],
        ]);

        foreach (ProductSortColumn::cases() as $column) {
            foreach (['asc', 'desc'] as $direction) {
                $this->actingAs($this->admin)
                    ->get($this->url('index', ['sort' => $column->value, 'direction' => $direction]))
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page
                        ->has('products.data', 2)
                        ->where('filters.sort', $column->value)
                        ->where('filters.direction', $direction));
            }
        }
    }

    public function test_the_list_is_paginated(): void
    {
        $this->inStore(function (): void {
            $category = Category::factory()->create();
            $unit = Unit::factory()->create();

            Product::factory()->count(26)->sequence(fn ($sequence) => ['name' => sprintf('Barang %02d', $sequence->index)])
                ->create(['category_id' => $category->id, 'base_unit_code' => $unit->code]);
        });

        $get = fn (array $query = []) => $this->actingAs($this->admin)->get($this->url('index', $query));

        $get()->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 25)
            ->where('products.total', 26)
            ->where('products.last_page', 2)
            ->where('products.data.0.name', 'Barang 00')
        );
        $get(['page' => 2])->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', 'Barang 25'));
    }

    public function test_the_list_shows_stock_per_warehouse_type_and_the_below_minimum_flag(): void
    {
        $this->create(['min_stock' => 10]);
        $product = $this->savedProduct();

        $this->inStore(function () use ($product): void {
            $main = Warehouse::query()->where('code', 'MAIN')->firstOrFail();
            $dead = Warehouse::query()->where('code', 'DEAD')->firstOrFail();

            Stock::factory()->create(['product_id' => $product->id, 'warehouse_id' => $main->id, 'quantity' => 7]);
            Stock::factory()->create(['product_id' => $product->id, 'warehouse_id' => $dead->id, 'quantity' => 2]);
        });

        $this->actingAs($this->admin)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.sellable_stock', fn ($quantity) => (float) $quantity === 7.0)
            ->where('products.data.0.dead_stock', fn ($quantity) => (float) $quantity === 2.0)
            ->where('products.data.0.is_below_minimum', true)
            ->where('products.data.0.base_unit', 'PAIL')
            ->where('products.data.0.default_sale', ['unit' => 'PAIL', 'price' => '116600.00'])
            ->where('products.data.0.units.1', ['code' => 'DUS', 'conversion_factor' => '4.000', 'is_sealed_pack' => false])
            ->where('products.data.0.sealed_pack', null)
            ->where('products.data.0.edit_url', $this->url('edit', ['product' => $product->id]))
        );
    }

    public function test_every_unit_keeps_its_own_purchase_price_and_the_base_one_is_the_product_cost(): void
    {
        $this->create([
            'units' => [
                $this->unitRow('PAIL', 1, '116600', isDefault: true, cost: '72875'),
                $this->unitRow('DUS', 4, '437250', cost: '280000'),
                $this->unitRow('LUSIN', 12, '1300000', cost: '0'),
            ],
        ])->assertSessionHasNoErrors();

        $product = $this->savedProduct();

        $this->assertSame('72875.00', $product->cost_price);
        $this->assertSame(['72875.00', '280000.00', '0.00'], $product->productUnits->pluck('cost_price')->all());
    }

    public function test_changing_one_units_purchase_price_leaves_the_others_alone(): void
    {
        $this->create();
        $product = $this->savedProduct();
        [$base, $box] = $product->productUnits->all();

        $units = fn (string $baseCost, string $boxCost) => [
            $this->unitRow('PAIL', 1, '116600', isDefault: true, id: $base->id, cost: $baseCost),
            $this->unitRow('DUS', 4, '437250', id: $box->id, cost: $boxCost),
        ];

        $this->actingAs($this->admin)
            ->put($this->url('update', ['product' => $product->id]), $this->editPayload($product, ['units' => $units('72875', '275000')]))
            ->assertSessionHasNoErrors();

        $updated = $this->savedProduct();

        $this->assertSame('72875.00', $updated->cost_price);
        $this->assertSame(['72875.00', '275000.00'], $updated->productUnits->pluck('cost_price')->all());

        $this->actingAs($this->admin)
            ->put($this->url('update', ['product' => $product->id]), $this->editPayload($product, ['units' => $units('80000', '275000')]))
            ->assertSessionHasNoErrors();

        $updated = $this->savedProduct();

        $this->assertSame('80000.00', $updated->cost_price);
        $this->assertSame(['80000.00', '275000.00'], $updated->productUnits->pluck('cost_price')->all());
    }

    public function test_a_locked_item_can_still_change_its_purchase_prices(): void
    {
        [$product, $base, $box] = $this->itemWithStock();

        $this->actingAs($this->admin)->put($this->url('update', ['product' => $product->id]), $this->editPayload($product, [
            'units' => [
                $this->unitRow('PAIL', 1, '116600', isDefault: true, id: $base->id, cost: '75000'),
                $this->unitRow('DUS', 4, '437250', id: $box->id, cost: '290000'),
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(['75000.00', '290000.00'], $this->savedProduct()->productUnits->pluck('cost_price')->all());
    }

    public function test_the_list_shows_the_base_unit_cost_as_the_modal(): void
    {
        $this->create();

        $this->actingAs($this->admin)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('products.data.0.cost_price', '72875.00')
        );
    }

    private function deleteItem(Product $product): TestResponse
    {
        return $this->actingAs($this->admin)->delete($this->url('destroy', ['product' => $product->id]));
    }

    private function assertItemStillExists(Product $product): void
    {
        $this->assertTrue($this->inStore(fn () => Product::query()->whereKey($product->id)->exists()));
    }

    public function test_an_item_without_stock_or_history_can_be_deleted_and_its_sku_reused(): void
    {
        $this->create();
        $product = $this->savedProduct();

        $this->inStore(fn () => Stock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => Warehouse::query()->where('code', 'MAIN')->value('id'),
            'quantity' => 0,
        ]));

        $this->deleteItem($product)->assertRedirect($this->url('index'))->assertSessionHas('success', 'Barang dihapus.');

        $this->inStore(function (): void {
            $this->assertSame(0, Product::withTrashed()->count());
            $this->assertSame(0, ProductUnit::query()->count());
            $this->assertSame(0, Stock::query()->count());
            $this->assertSame(1, Category::query()->count());
            $this->assertSame(1, Brand::query()->count());
            $this->assertSame(2, Unit::query()->count());
        });

        $this->create()->assertSessionHasNoErrors();
        $this->assertSame('MU0001', $this->savedProduct()->sku);
    }

    public function test_an_item_with_stock_cannot_be_deleted(): void
    {
        [$product] = $this->itemWithStock();

        $this->deleteItem($product)->assertSessionHas('error');

        $this->assertItemStillExists($product);
        $this->assertSame(2, $this->inStore(fn () => ProductUnit::query()->count()));
    }

    public function test_an_item_with_a_stock_movement_cannot_be_deleted(): void
    {
        $this->create();
        $product = $this->savedProduct();

        $this->inStore(fn () => DB::table('stock_movements')->insert([
            'product_id' => $product->id,
            'warehouse_id' => Warehouse::query()->where('code', 'MAIN')->value('id'),
            'moved_at' => now(),
            'type' => 'opening_balance',
            'quantity' => 5,
            'balance_after' => 5,
        ]));

        $this->deleteItem($product)->assertSessionHas('error');

        $this->assertItemStillExists($product);
    }

    public function test_an_item_listed_in_an_opname_cannot_be_deleted(): void
    {
        $this->create();
        $product = $this->savedProduct();

        $this->inStore(function () use ($product): void {
            $opnameId = DB::table('stock_opnames')->insertGetId([
                'number' => 'OP/0001',
                'warehouse_id' => Warehouse::query()->where('code', 'MAIN')->value('id'),
                'opname_date' => today(),
                'scope' => 'selected',
                'created_by' => 1,
            ]);

            DB::table('stock_opname_items')->insert(['stock_opname_id' => $opnameId, 'product_id' => $product->id, 'snapshot_quantity' => 0]);
        });

        $this->deleteItem($product)->assertSessionHas('error');

        $this->assertItemStillExists($product);
    }

    public function test_the_list_and_form_say_whether_an_item_may_be_deleted(): void
    {
        $this->create(['sku' => 'FREE']);
        $this->create(['sku' => 'BUSY', 'name' => 'Barang Ada Stok', 'units' => [$this->unitRow('PCS', 1, '1000', isDefault: true)]]);
        $busy = $this->savedProduct('BUSY');

        $this->inStore(fn () => Stock::factory()->create([
            'product_id' => $busy->id,
            'warehouse_id' => Warehouse::query()->where('code', 'MAIN')->value('id'),
            'quantity' => 3,
        ]));

        $this->actingAs($this->admin)->get($this->url('index'))->assertInertia(fn (Assert $page) => $page
            ->where('products.data', fn ($rows) => collect($rows)->pluck('can_delete', 'sku')->all() === ['BUSY' => false, 'FREE' => true])
        );

        $free = $this->savedProduct('FREE');

        $this->actingAs($this->admin)->get($this->url('edit', ['product' => $free->id]))->assertInertia(fn (Assert $page) => $page
            ->where('product.is_locked', false)
            ->where('product.delete_url', $this->url('destroy', ['product' => $free->id]))
        );
    }

    /**
     * The two units of the default payload with their ids, as the form sends them back unchanged.
     *
     * @return list<array<string, mixed>>
     */
    private function unchangedUnits(Product $product): array
    {
        [$base, $box] = $product->productUnits->all();

        return [
            $this->unitRow('PAIL', 1, '116600', isDefault: true, id: $base->id, cost: '72875'),
            $this->unitRow('DUS', 4, '437250', id: $box->id, cost: '280000'),
        ];
    }

    public function test_every_save_counts_up_the_version_even_when_only_a_price_changed(): void
    {
        $this->create();
        $product = $this->savedProduct();
        $this->assertSame(1, $product->version);
        $units = $this->unchangedUnits($product);
        $units[1]['selling_price'] = '999999';

        $this->actingAs($this->admin)->put($this->url('update', ['product' => $product->id]), $this->editPayload($product, ['units' => $units]))->assertSessionHasNoErrors();

        $this->assertSame(2, $this->savedProduct()->version);
    }

    public function test_a_save_from_an_older_version_is_refused_instead_of_overwriting_the_newer_one(): void
    {
        $this->create();
        $product = $this->savedProduct();
        $openedByBoth = $this->editPayload($product, ['units' => $this->unchangedUnits($product)]);

        $this->actingAs($this->admin)->put($this->url('update', ['product' => $product->id]), [...$openedByBoth, 'name' => 'Nama pertama'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->put($this->url('update', ['product' => $product->id]), [...$openedByBoth, 'name' => 'Nama kedua'])
            ->assertSessionHas('error', 'Barang ini diubah orang lain sejak halaman dibuka. Muat ulang halaman, lalu isi lagi perubahan Anda.');

        $this->assertSame('Nama pertama', $this->savedProduct()->name);
    }

    public function test_an_item_form_opened_before_a_bulk_price_change_is_refused(): void
    {
        $this->create();
        $product = $this->savedProduct();
        $openedBeforeBulkChange = $this->editPayload($product, ['units' => $this->unchangedUnits($product)]);
        $change = ['product_ids' => [$product->id], 'unit_code' => 'PAIL', 'selling_method' => 'percent', 'selling_value' => 10];
        $fingerprint = $this->actingAs($this->admin)->postJson(route('tenant.bulk-prices.preview', ['tenant' => $this->store->code]), $change)->json('data.fingerprint');

        $this->actingAs($this->admin)->post(route('tenant.bulk-prices.apply', ['tenant' => $this->store->code]), [...$change, 'preview_fingerprint' => $fingerprint])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->put($this->url('update', ['product' => $product->id]), [...$openedBeforeBulkChange, 'name' => 'Nama dari form lama'])
            ->assertSessionHas('error', 'Barang ini diubah orang lain sejak halaman dibuka. Muat ulang halaman, lalu isi lagi perubahan Anda.');

        $this->assertNotSame('Nama dari form lama', $this->savedProduct()->name);
    }

    public function test_an_edit_needs_the_version_it_was_opened_with(): void
    {
        $this->create();
        $product = $this->savedProduct();

        $this->actingAs($this->admin)->put($this->url('update', ['product' => $product->id]), $this->payload(['units' => $this->unchangedUnits($product)]))
            ->assertSessionHasErrors('version');
    }

    public function test_the_edit_form_carries_the_version(): void
    {
        $this->create();
        $product = $this->savedProduct();

        $this->actingAs($this->admin)->get($this->url('edit', ['product' => $product->id]))->assertInertia(fn (Assert $page) => $page->where('product.version', 1));
    }

    public function test_the_locked_unit_rules_are_checked_again_when_saving(): void
    {
        [$product] = $this->itemWithStock();
        $unitsWithoutTheBox = [$this->unchangedUnits($product)[0]];

        $this->expectException(ProductSaveException::class);

        $this->inStore(fn () => app(ProductService::class)->update($product, [...$this->payload(['units' => $unitsWithoutTheBox]), 'version' => $product->version]));
    }

    public function test_two_rows_swapping_their_units_are_asked_to_be_changed_one_at_a_time(): void
    {
        $this->create();
        $product = $this->savedProduct();
        $units = $this->unchangedUnits($product);
        $swapped = [
            [...$units[0], 'unit' => ['code' => 'DUS']],
            [...$units[1], 'unit' => ['code' => 'PAIL']],
        ];

        $this->actingAs($this->admin)->put($this->url('update', ['product' => $product->id]), $this->editPayload($product, ['units' => $swapped]))
            ->assertSessionHasErrors(['units.0.unit.code' => 'Satuan ini masih dipakai baris lain yang ikut diganti. Ubah satu baris dulu, simpan, lalu ubah yang lain.']);

        $this->assertSame(['PAIL', 'DUS'], $this->savedProduct()->productUnits->pluck('unit_code')->all());
    }

    public function test_removing_a_unit_keeps_an_audit_record_of_its_last_prices(): void
    {
        config(['audit.console' => true]);
        $this->create();
        $product = $this->savedProduct();
        $box = $product->productUnits[1];

        $this->actingAs($this->admin)->put($this->url('update', ['product' => $product->id]), $this->editPayload($product, ['units' => [$this->unchangedUnits($product)[0]]]))->assertSessionHasNoErrors();

        $audit = $this->inStore(fn () => Audit::query()->where('auditable_type', ProductUnit::class)->where('auditable_id', $box->id)->where('event', 'deleted')->sole());
        $this->assertEquals([437250, 280000], [$audit->old_values['selling_price'], $audit->old_values['cost_price']]);
    }

    public function test_an_item_that_was_sold_cannot_be_deleted_or_have_its_units_changed(): void
    {
        $this->create();
        $product = $this->savedProduct();
        $this->inStore(fn () => SaleItem::factory()->create(['product_id' => $product->id]));

        $this->deleteItem($product)->assertSessionHas('error');
        $this->assertItemStillExists($product);

        $this->actingAs($this->admin)->put($this->url('update', ['product' => $product->id]), $this->editPayload($product, ['units' => [$this->unchangedUnits($product)[0]]]))
            ->assertSessionHasErrors(['units' => 'Satuan yang sudah dipakai tidak bisa dihapus.']);
        $this->assertCount(2, $this->savedProduct()->productUnits);
    }

    public function test_a_percent_or_underscore_in_the_search_is_not_a_wildcard(): void
    {
        $this->seedItems([
            ['name' => 'Diskon 50% Cat', 'sku' => 'A1'],
            ['name' => 'Cat 500 gram', 'sku' => 'A2'],
            ['name' => 'Cat_Putih', 'sku' => 'A3'],
            ['name' => 'Cat Hitam', 'sku' => 'A4'],
        ]);

        $this->actingAs($this->admin)->get($this->url('index', ['search' => '50%']))->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.sku', 'A1'));
        $this->actingAs($this->admin)->get($this->url('index', ['search' => 't_p']))->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.sku', 'A3'));
    }

    public function test_the_list_filters_by_status_and_ignores_a_status_it_does_not_know(): void
    {
        $this->seedItems([['name' => 'Paku', 'sku' => 'A1'], ['name' => 'Lampu', 'sku' => 'A2', 'active' => false]]);

        $this->actingAs($this->admin)->get($this->url('index', ['status' => 'inactive']))->assertInertia(fn (Assert $page) => $page
            ->has('statuses', 2)
            ->has('products.data', 1)
            ->where('products.data.0.sku', 'A2'));
        $this->actingAs($this->admin)->get($this->url('index', ['status' => 'bukan-status']))->assertInertia(fn (Assert $page) => $page->has('products.data', 2));
    }
}

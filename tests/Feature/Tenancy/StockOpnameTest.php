<?php

namespace Tests\Feature\Tenancy;

use App\Enums\StockMovementType;
use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Store;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockOpnameReport;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_opname';

    private Store $store;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_opname', 'receipt_prefix' => 'TOP']);
        $this->admin = $this->userWithRole(UserRole::StoreAdmin);
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $this->store->users()->attach($user->id, ['role' => $role->value]);

        return $user;
    }

    private function url(string $route, array $parameters = []): string
    {
        return route("tenant.stock-opnames.{$route}", ['tenant' => $this->store->code, ...$parameters]);
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

    private function warehouse(string $code = 'MAIN'): Warehouse
    {
        return $this->inStore(fn () => Warehouse::query()->where('code', $code)->firstOrFail());
    }

    /**
     * A product counted in PAIL (base) and sold by the DUS of four, with the given stock in the main warehouse.
     */
    private function makeProduct(string $name, string $stock = '0', ?Category $category = null, string $cost = '1000', ?Brand $brand = null): Product
    {
        return $this->inStore(function () use ($name, $stock, $category, $cost, $brand): Product {
            $pail = Unit::query()->firstOrCreate(['code' => 'PAIL'], ['name' => 'Pail']);
            $dus = Unit::query()->firstOrCreate(['code' => 'DUS'], ['name' => 'Dus']);

            $product = Product::factory()->create(array_filter([
                'name' => $name,
                'base_unit_code' => $pail->code,
                'category_id' => $category?->id,
                'brand_id' => $brand?->id,
                'cost_price' => $cost,
            ]));
            ProductUnit::factory()->create(['product_id' => $product->id, 'unit_code' => $pail->code, 'conversion_factor' => 1, 'sort_order' => 0]);
            ProductUnit::factory()->create(['product_id' => $product->id, 'unit_code' => $dus->code, 'conversion_factor' => 4, 'is_base' => false, 'is_default_sale' => false, 'sort_order' => 1]);

            $this->setStock($product, $stock);

            return $product;
        });
    }

    private function setStock(Product $product, string $quantity, ?Warehouse $warehouse = null): void
    {
        $warehouse ??= $this->warehouse();

        $this->inStore(fn () => Stock::query()->updateOrInsert(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['quantity' => $quantity],
        ));
    }

    private function stockOf(Product $product, ?Warehouse $warehouse = null): string
    {
        $warehouse ??= $this->warehouse();

        return $this->inStore(fn () => (string) (Stock::query()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity') ?? '0.000'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOpname(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin)->post($this->url('store'), [
            'warehouse_id' => $this->warehouse()->id,
            'opname_date' => '2026-10-24',
            'scope' => StockOpnameScope::Selected->value,
            'reason_code' => StockOpnameReason::Correction->value,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function opname(array $attributes = []): StockOpname
    {
        return $this->inStore(fn () => StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse()->id,
            'reason_code' => StockOpnameReason::Correction,
            'created_by' => $this->admin->id,
            ...$attributes,
        ]));
    }

    private function opnameInStatus(StockOpnameStatus $status, StockOpnameScope $scope = StockOpnameScope::Selected): StockOpname
    {
        return $this->opname(['status' => $status, 'scope' => $scope, 'snapshot_at' => $status === StockOpnameStatus::Draft ? null : now()]);
    }

    private function addItem(StockOpname $opname, Product $product, ?string $counted = null, string $snapshot = '0'): StockOpnameItem
    {
        return $this->inStore(fn () => StockOpnameItem::factory()->create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'snapshot_quantity' => $snapshot,
            'counted_quantity' => $counted,
        ]));
    }

    private function item(StockOpname $opname, Product $product): StockOpnameItem
    {
        return $this->inStore(fn () => StockOpnameItem::query()->where('stock_opname_id', $opname->id)->where('product_id', $product->id)->firstOrFail());
    }

    private function statusOf(StockOpname $opname): StockOpnameStatus
    {
        return $this->inStore(fn () => $opname->fresh()->status);
    }

    /**
     * @param  list<array{unit_code: string, quantity: string|null}>  $parts
     */
    private function saveCount(StockOpname $opname, StockOpnameItem $item, array $parts, ?string $reason = null): TestResponse
    {
        return $this->actingAs($this->admin)->put($this->url('counts', ['stock_opname' => $opname->id]), [
            'counts' => [['item_id' => $item->id, 'parts' => $parts, 'reason' => $reason]],
        ]);
    }

    private function action(string $route, StockOpname $opname): TestResponse
    {
        return $this->actingAs($this->admin)->post($this->url($route, ['stock_opname' => $opname->id]));
    }

    public function test_only_admins_may_use_stock_opname(): void
    {
        $opname = $this->opname();

        $this->actingAs($this->userWithRole(UserRole::Cashier));
        $this->get($this->url('index'))->assertForbidden();
        $this->get($this->url('create'))->assertForbidden();
        $this->post($this->url('store'), [])->assertForbidden();
        $this->get($this->url('show', ['stock_opname' => $opname->id]))->assertForbidden();
        $this->post($this->url('post', ['stock_opname' => $opname->id]))->assertForbidden();
    }

    public function test_guest_is_sent_to_the_login_page(): void
    {
        $this->get($this->url('index'))->assertRedirect(route('login'));
    }

    public function test_the_list_shows_opnames_newest_first_with_counting_progress(): void
    {
        $older = $this->opname(['opname_date' => '2026-10-01', 'number' => 'OPN/TOP/2610/0001']);
        $newer = $this->opname(['opname_date' => '2026-10-20', 'number' => 'OPN/TOP/2610/0002', 'status' => StockOpnameStatus::Counting]);
        $this->addItem($newer, $this->makeProduct('Cat A'), '3');
        $this->addItem($newer, $this->makeProduct('Cat B'));

        $this->actingAs($this->admin)->get($this->url('index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/StockOpnames/Index')
            ->has('opnames.data', 2)
            ->where('opnames.data.0.number', 'OPN/TOP/2610/0002')
            ->where('opnames.data.0.status_label', 'Menghitung')
            ->where('opnames.data.0.items_count', 2)
            ->where('opnames.data.0.counted_items_count', 1)
            ->where('opnames.data.1.number', $older->number)
        );
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        $this->opname(['status' => StockOpnameStatus::Posted]);
        $this->opname(['status' => StockOpnameStatus::Draft]);

        $this->actingAs($this->admin)->get($this->url('index', ['status' => 'posted']))
            ->assertInertia(fn (Assert $page) => $page->has('opnames.data', 1)->where('opnames.data.0.status', 'posted'));
    }

    public function test_an_opname_is_created_as_a_draft_with_a_numbered_document(): void
    {
        $response = $this->createOpname();

        $opname = $this->inStore(fn () => StockOpname::query()->firstOrFail());
        $response->assertRedirect($this->url('show', ['stock_opname' => $opname->id]))->assertSessionHas('success');

        $this->assertSame('OPN/TOP/2610/0001', $opname->number);
        $this->assertSame(StockOpnameStatus::Draft, $opname->status);
        $this->assertSame($this->admin->id, $opname->created_by);
        $this->assertSame(StockOpnameReason::Correction, $opname->reason_code);
    }

    public function test_numbers_run_on_within_a_month_and_restart_in_the_next(): void
    {
        $this->createOpname(['opname_date' => '2026-10-02']);
        $this->createOpname(['opname_date' => '2026-10-28']);
        $this->createOpname(['opname_date' => '2026-11-03']);

        $numbers = $this->inStore(fn () => StockOpname::query()->orderBy('id')->pluck('number')->all());

        $this->assertSame(['OPN/TOP/2610/0001', 'OPN/TOP/2610/0002', 'OPN/TOP/2611/0001'], $numbers);
    }

    public function test_the_header_is_validated(): void
    {
        $this->createOpname(['warehouse_id' => null, 'opname_date' => null, 'scope' => null, 'reason_code' => null])
            ->assertSessionHasErrors(['warehouse_id', 'opname_date', 'scope', 'reason_code']);
        $this->createOpname(['scope' => 'all'])->assertSessionHasErrors('scope');
        $this->createOpname(['scope' => 'everything'])->assertSessionHasErrors('scope');
        $this->createOpname(['reason_code' => 'because'])->assertSessionHasErrors('reason_code');
        $this->createOpname(['warehouse_id' => 9999])->assertSessionHasErrors('warehouse_id');

        $this->assertSame(0, $this->inStore(fn () => StockOpname::query()->count()));
    }

    public function test_an_inactive_warehouse_cannot_be_counted(): void
    {
        $this->inStore(fn () => Warehouse::query()->where('code', 'DEAD')->update(['is_active' => false]));

        $this->createOpname(['warehouse_id' => $this->warehouse('DEAD')->id])->assertSessionHasErrors('warehouse_id');
    }

    public function test_the_category_and_brand_scope_needs_at_least_one_of_them(): void
    {
        $category = $this->inStore(fn () => Category::factory()->create());
        $brand = $this->inStore(fn () => Brand::factory()->create());

        $message = 'Pilih kategori atau merek, boleh salah satu.';
        $this->createOpname(['scope' => 'category'])->assertSessionHasErrors(['category_id' => $message, 'brand_id' => $message]);
        $this->createOpname(['scope' => 'category', 'category_id' => 9999])->assertSessionHasErrors('category_id');
        $this->createOpname(['scope' => 'category', 'brand_id' => 9999])->assertSessionHasErrors('brand_id');
        $this->assertSame(0, $this->inStore(fn () => StockOpname::query()->count()));

        $this->createOpname(['scope' => 'category', 'category_id' => $category->id])->assertSessionHasNoErrors();
        $this->createOpname(['scope' => 'category', 'brand_id' => $brand->id])->assertSessionHasNoErrors();
        $this->createOpname(['scope' => 'category', 'category_id' => $category->id, 'brand_id' => $brand->id])->assertSessionHasNoErrors();

        $this->assertSame(
            [[$category->id, null], [null, $brand->id], [$category->id, $brand->id]],
            $this->inStore(fn () => StockOpname::query()->orderBy('id')->get()->map(fn (StockOpname $opname): array => [$opname->category_id, $opname->brand_id])->all()),
        );
    }

    public function test_a_category_and_brand_are_only_kept_for_the_category_scope(): void
    {
        $category = $this->inStore(fn () => Category::factory()->create());
        $brand = $this->inStore(fn () => Brand::factory()->create());

        $this->createOpname(['scope' => 'selected', 'category_id' => $category->id, 'brand_id' => $brand->id]);

        $opname = $this->inStore(fn () => StockOpname::query()->firstOrFail());
        $this->assertNull($opname->category_id);
        $this->assertNull($opname->brand_id);
    }

    public function test_the_create_page_offers_active_warehouses_scopes_and_reasons(): void
    {
        $this->actingAs($this->admin)->get($this->url('create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/StockOpnames/Create')
            ->where('warehouses.0.id', $this->warehouse()->id)
            ->has('warehouses', 2)
            ->has('scopes', 2)
            ->where('scopes.0.label', 'Kategori & merek tertentu')
            ->has('brands')
            ->has('reasons', 7)
            ->where('store_url', $this->url('store'))
        );
    }

    /**
     * @param  array<string, mixed>  $filter  `category_id` and/or `brand_id`
     * @return list<int> ids of the products an opname with that filter holds after it starts
     */
    private function startedCategoryOpnameProductIds(array $filter): array
    {
        $opname = $this->opname(['scope' => StockOpnameScope::Category, ...$filter]);

        $this->action('start', $opname);

        return $this->inStore(fn () => $opname->items()->orderBy('product_id')->pluck('product_id')->all());
    }

    public function test_starting_a_category_opname_snapshots_the_products_of_that_category(): void
    {
        $paints = $this->inStore(fn () => Category::factory()->create());
        $paint = $this->makeProduct('Cat', '24', $paints);
        $brush = $this->makeProduct('Kuas', '0', $paints);
        $nails = $this->makeProduct('Paku', '9');
        $opname = $this->opname(['scope' => StockOpnameScope::Category, 'category_id' => $paints->id]);

        $this->action('start', $opname)->assertSessionHas('success');

        $this->assertSame(StockOpnameStatus::Counting, $this->statusOf($opname));
        $this->assertNotNull($this->inStore(fn () => $opname->fresh()->snapshot_at));
        $this->assertSame('24.000', $this->item($opname, $paint)->snapshot_quantity);
        $this->assertSame('0.000', $this->item($opname, $brush)->snapshot_quantity);
        $this->assertNull($this->item($opname, $paint)->counted_quantity);
        $this->assertSame(2, $this->inStore(fn () => $opname->items()->count()));
        $this->assertFalse($this->inStore(fn () => $opname->items()->where('product_id', $nails->id)->exists()));
    }

    public function test_a_brand_only_opname_takes_that_brand_across_categories(): void
    {
        $nippon = $this->inStore(fn () => Brand::factory()->create());
        $other = $this->inStore(fn () => Brand::factory()->create());
        $paints = $this->inStore(fn () => Category::factory()->create());
        $tools = $this->inStore(fn () => Category::factory()->create());
        $nipponPaint = $this->makeProduct('Cat Nippon', '1', $paints, '1000', $nippon);
        $nipponBrush = $this->makeProduct('Kuas Nippon', '1', $tools, '1000', $nippon);
        $this->makeProduct('Cat Lain', '1', $paints, '1000', $other);
        $this->makeProduct('Tanpa merek', '1', $paints);

        $this->assertSame([$nipponPaint->id, $nipponBrush->id], $this->startedCategoryOpnameProductIds(['brand_id' => $nippon->id]));
    }

    public function test_a_category_and_brand_opname_takes_only_products_matching_both(): void
    {
        $nippon = $this->inStore(fn () => Brand::factory()->create());
        $other = $this->inStore(fn () => Brand::factory()->create());
        $paints = $this->inStore(fn () => Category::factory()->create());
        $tools = $this->inStore(fn () => Category::factory()->create());
        $match = $this->makeProduct('Cat Nippon', '1', $paints, '1000', $nippon);
        $this->makeProduct('Kuas Nippon', '1', $tools, '1000', $nippon);
        $this->makeProduct('Cat Lain', '1', $paints, '1000', $other);

        $this->assertSame([$match->id], $this->startedCategoryOpnameProductIds(['category_id' => $paints->id, 'brand_id' => $nippon->id]));
    }

    public function test_a_category_opname_with_no_matching_products_cannot_start(): void
    {
        $empty = $this->inStore(fn () => Category::factory()->create());
        $opname = $this->opname(['scope' => StockOpnameScope::Category, 'category_id' => $empty->id]);

        $this->action('start', $opname)->assertSessionHas('error', 'Tidak ada barang untuk kategori dan merek itu.');

        $this->assertSame(StockOpnameStatus::Draft, $this->statusOf($opname));
        $this->assertSame(0, $this->inStore(fn () => $opname->items()->count()));
    }

    public function test_the_show_page_names_the_category_and_brand(): void
    {
        $paints = $this->inStore(fn () => Category::factory()->create(['name' => 'Cat']));
        $nippon = $this->inStore(fn () => Brand::factory()->create(['name' => 'Nippon']));
        $opname = $this->opname(['scope' => StockOpnameScope::Category, 'category_id' => $paints->id, 'brand_id' => $nippon->id]);

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]))->assertInertia(fn (Assert $page) => $page
            ->where('opname.scope', 'category')
            ->where('opname.category', 'Cat')
            ->where('opname.brand', 'Nippon')
            ->where('opname.can_manage_products', false));
    }

    public function test_a_selected_opname_cannot_start_without_products(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Draft, StockOpnameScope::Selected);

        $this->action('start', $opname)->assertSessionHas('error', 'Pilih minimal satu barang sebelum mulai menghitung.');

        $this->assertSame(StockOpnameStatus::Draft, $this->statusOf($opname));
    }

    public function test_a_selected_opname_refreshes_its_snapshot_when_it_starts(): void
    {
        $paint = $this->makeProduct('Cat', '10');
        $opname = $this->opnameInStatus(StockOpnameStatus::Draft, StockOpnameScope::Selected);
        $this->addItem($opname, $paint, null, '10');
        $this->setStock($paint, '14');

        $this->action('start', $opname);

        $this->assertSame('14.000', $this->item($opname, $paint)->snapshot_quantity);
        $this->assertSame(StockOpnameStatus::Counting, $this->statusOf($opname));
    }

    public function test_products_can_be_added_to_and_removed_from_a_selected_opname(): void
    {
        $paint = $this->makeProduct('Cat', '7');
        $opname = $this->opnameInStatus(StockOpnameStatus::Draft, StockOpnameScope::Selected);

        $this->actingAs($this->admin)->post($this->url('items.store', ['stock_opname' => $opname->id]), ['product_id' => $paint->id])
            ->assertSessionHas('success');
        $this->actingAs($this->admin)->post($this->url('items.store', ['stock_opname' => $opname->id]), ['product_id' => $paint->id]);

        $item = $this->item($opname, $paint);
        $this->assertSame(1, $this->inStore(fn () => $opname->items()->count()));
        $this->assertSame('7.000', $item->snapshot_quantity);

        $this->actingAs($this->admin)->delete($this->url('items.destroy', ['stock_opname' => $opname->id, 'item' => $item->id]))
            ->assertSessionHas('success');

        $this->assertSame(0, $this->inStore(fn () => $opname->items()->count()));
    }

    public function test_products_cannot_be_added_to_a_category_opname(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Counting, StockOpnameScope::Category);

        $this->actingAs($this->admin)
            ->post($this->url('items.store', ['stock_opname' => $opname->id]), ['product_id' => $this->makeProduct('Cat')->id])
            ->assertSessionHas('error');

        $this->assertSame(0, $this->inStore(fn () => $opname->items()->count()));
    }

    public function test_an_item_of_another_opname_cannot_be_removed_through_this_one(): void
    {
        $mine = $this->opnameInStatus(StockOpnameStatus::Counting, StockOpnameScope::Selected);
        $other = $this->opnameInStatus(StockOpnameStatus::Counting, StockOpnameScope::Selected);
        $item = $this->addItem($other, $this->makeProduct('Cat'));

        $this->actingAs($this->admin)
            ->delete($this->url('items.destroy', ['stock_opname' => $mine->id, 'item' => $item->id]))
            ->assertNotFound();

        $this->assertSame(1, $this->inStore(fn () => $other->items()->count()));
    }

    public function test_a_product_cannot_be_in_two_active_opnames_of_the_same_warehouse(): void
    {
        $paint = $this->makeProduct('Cat');
        $first = $this->opnameInStatus(StockOpnameStatus::Counting, StockOpnameScope::Selected);
        $this->addItem($first, $paint);
        $second = $this->opnameInStatus(StockOpnameStatus::Draft, StockOpnameScope::Selected);

        $this->actingAs($this->admin)->post($this->url('items.store', ['stock_opname' => $second->id]), ['product_id' => $paint->id])
            ->assertSessionHas('error', 'Cat sedang dihitung di opname lain untuk gudang ini.');

        $this->assertSame(0, $this->inStore(fn () => $second->items()->count()));
    }

    public function test_a_finished_opname_no_longer_holds_its_products(): void
    {
        $paint = $this->makeProduct('Cat');
        $posted = $this->opnameInStatus(StockOpnameStatus::Posted, StockOpnameScope::Selected);
        $this->addItem($posted, $paint);
        $cancelled = $this->opnameInStatus(StockOpnameStatus::Cancelled, StockOpnameScope::Selected);
        $this->addItem($cancelled, $paint);
        $next = $this->opnameInStatus(StockOpnameStatus::Draft, StockOpnameScope::Selected);

        $this->actingAs($this->admin)->post($this->url('items.store', ['stock_opname' => $next->id]), ['product_id' => $paint->id])
            ->assertSessionHas('success');
    }

    public function test_the_same_product_may_be_counted_in_another_warehouse(): void
    {
        $paint = $this->makeProduct('Cat');
        $mainOpname = $this->opnameInStatus(StockOpnameStatus::Counting, StockOpnameScope::Selected);
        $this->addItem($mainOpname, $paint);
        $deadOpname = $this->opname(['warehouse_id' => $this->warehouse('DEAD')->id, 'scope' => StockOpnameScope::Selected]);

        $this->actingAs($this->admin)->post($this->url('items.store', ['stock_opname' => $deadOpname->id]), ['product_id' => $paint->id])
            ->assertSessionHas('success');
    }

    public function test_starting_a_category_opname_leaves_out_products_held_elsewhere_and_says_so(): void
    {
        $paints = $this->inStore(fn () => Category::factory()->create());
        $held = $this->makeProduct('Cat', '0', $paints);
        $free = $this->makeProduct('Kuas', '0', $paints);
        $other = $this->opnameInStatus(StockOpnameStatus::Counting, StockOpnameScope::Selected);
        $this->addItem($other, $held);
        $opname = $this->opname(['scope' => StockOpnameScope::Category, 'category_id' => $paints->id]);

        $this->action('start', $opname)->assertSessionHas('success', fn (string $message): bool => str_contains($message, '1 barang dilewati'));

        $this->assertSame([$free->id], $this->inStore(fn () => $opname->items()->pluck('product_id')->all()));
    }

    public function test_counts_in_mixed_units_are_added_up_in_the_base_unit(): void
    {
        $paint = $this->makeProduct('Cat', '24');
        $opname = $this->opnameInStatus(StockOpnameStatus::Counting);
        $item = $this->addItem($opname, $paint, null, '24');

        $this->saveCount($opname, $item, [['unit_code' => 'DUS', 'quantity' => '5'], ['unit_code' => 'PAIL', 'quantity' => '3']], 'tumpah')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $item = $item->fresh();
        $this->assertSame('23.000', $item->counted_quantity);
        $this->assertSame('tumpah', $item->reason);
    }

    public function test_an_empty_count_marks_the_item_as_not_counted_again(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Counting);
        $item = $this->addItem($opname, $this->makeProduct('Cat'), '5');

        $this->saveCount($opname, $item, [['unit_code' => 'PAIL', 'quantity' => null]])->assertSessionHasNoErrors();

        $this->assertNull($item->fresh()->counted_quantity);
    }

    public function test_zero_is_a_real_count_and_blank_is_not(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Counting);
        $item = $this->addItem($opname, $this->makeProduct('Cat'));

        $this->saveCount($opname, $item, [['unit_code' => 'PAIL', 'quantity' => '0']]);

        $this->assertSame('0.000', $item->fresh()->counted_quantity);
    }

    public function test_decimal_counts_are_kept_to_three_places(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Counting);
        $item = $this->addItem($opname, $this->makeProduct('Cat'));

        $this->saveCount($opname, $item, [['unit_code' => 'DUS', 'quantity' => '1.125']])->assertSessionHasNoErrors();

        $this->assertSame('4.500', $item->fresh()->counted_quantity);
    }

    public function test_count_input_is_validated(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Counting);
        $item = $this->addItem($opname, $this->makeProduct('Cat'));

        $this->saveCount($opname, $item, [['unit_code' => 'PAIL', 'quantity' => '-1']])->assertSessionHasErrors('counts.0.parts.0.quantity');
        $this->saveCount($opname, $item, [['unit_code' => 'PAIL', 'quantity' => 'abc']])->assertSessionHasErrors('counts.0.parts.0.quantity');
        $this->saveCount($opname, $item, [['unit_code' => 'PAIL', 'quantity' => '1.2345']])->assertSessionHasErrors('counts.0.parts.0.quantity');
        $this->saveCount($opname, $item, [['unit_code' => 'KG', 'quantity' => '1']])->assertSessionHasErrors('counts.0.parts.0.unit_code');

        $this->assertNull($item->fresh()->counted_quantity);
    }

    public function test_a_count_for_an_item_of_another_opname_is_rejected(): void
    {
        $mine = $this->opnameInStatus(StockOpnameStatus::Counting);
        $other = $this->opnameInStatus(StockOpnameStatus::Counting);
        $foreignItem = $this->addItem($other, $this->makeProduct('Cat'));

        $this->saveCount($mine, $foreignItem, [['unit_code' => 'PAIL', 'quantity' => '5']])->assertSessionHasErrors('counts.0.item_id');

        $this->assertNull($foreignItem->fresh()->counted_quantity);
    }

    public function test_counts_can_only_be_saved_while_counting(): void
    {
        foreach ([StockOpnameStatus::Draft, StockOpnameStatus::Review, StockOpnameStatus::Posted] as $status) {
            $opname = $this->opnameInStatus($status);
            $item = $this->addItem($opname, $this->makeProduct("Cat {$status->value}"));

            $this->saveCount($opname, $item, [['unit_code' => 'PAIL', 'quantity' => '5']])->assertSessionHas('error');

            $this->assertNull($item->fresh()->counted_quantity, $status->value);
        }
    }

    public function test_uncounted_items_can_be_marked_zero_on_purpose(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Counting);
        $counted = $this->addItem($opname, $this->makeProduct('Cat'), '5');
        $blank = $this->addItem($opname, $this->makeProduct('Kuas'));

        $this->action('zero-uncounted', $opname)->assertSessionHas('success');

        $this->assertSame('5.000', $counted->fresh()->counted_quantity);
        $this->assertSame('0.000', $blank->fresh()->counted_quantity);
    }

    public function test_a_routine_opname_where_every_typed_count_matches_changes_no_stock(): void
    {
        $paint = $this->makeProduct('Cat', '24');
        $brush = $this->makeProduct('Kuas', '38');
        $opname = $this->opname(['status' => StockOpnameStatus::Review, 'reason_code' => StockOpnameReason::Routine]);
        $this->addItem($opname, $paint, '24', '24');
        $this->addItem($opname, $brush, '38', '38');

        $this->action('post', $opname)->assertSessionHas('success');

        $this->assertSame(StockOpnameStatus::Posted, $this->statusOf($opname));
        $this->assertSame('24.000', $this->stockOf($paint));
        $this->assertSame('38.000', $this->stockOf($brush));
        $this->assertSame(0, $this->inStore(fn () => StockMovement::query()->count()));
        $this->assertSame('0.000', $this->item($opname, $paint)->variance);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function arrivalsOpname(StockOpnameStatus $status, array $attributes = []): StockOpname
    {
        return $this->opname(['status' => $status, 'reason_code' => StockOpnameReason::StockIncrease, ...$attributes]);
    }

    public function test_an_arrivals_opname_adds_the_typed_quantity_to_the_stock(): void
    {
        $paint = $this->makeProduct('Cat', '24');
        $opname = $this->arrivalsOpname(StockOpnameStatus::Review);
        $this->addItem($opname, $paint, '5', '24');

        $this->action('post', $opname)->assertSessionHas('success');

        $this->assertSame('29.000', $this->stockOf($paint));
        $movement = $this->inStore(fn () => StockMovement::query()->firstOrFail());
        $this->assertSame([StockMovementType::OpnameAdjustment, '5.000', '29.000', 'Penambahan stok'], [$movement->type, $movement->quantity, $movement->balance_after, $movement->note]);
        $item = $this->item($opname, $paint);
        $this->assertSame(['24.000', '5.000'], [$item->system_quantity_at_posting, $item->variance]);
    }

    public function test_an_arrival_is_added_to_the_live_stock_even_after_sales_since_the_snapshot(): void
    {
        $paint = $this->makeProduct('Cat', '24');
        $opname = $this->arrivalsOpname(StockOpnameStatus::Review);
        $this->addItem($opname, $paint, '5', '24');
        $this->setStock($paint, '20');

        $this->action('post', $opname);

        $this->assertSame('25.000', $this->stockOf($paint));
    }

    public function test_an_arrival_of_zero_and_an_item_left_blank_change_nothing(): void
    {
        $zero = $this->makeProduct('Cat', '24');
        $blank = $this->makeProduct('Kuas', '38');
        $opname = $this->arrivalsOpname(StockOpnameStatus::Review);
        $this->addItem($opname, $zero, '0', '24');
        $this->addItem($opname, $blank, null, '38');

        $this->action('post', $opname);

        $this->assertSame(['24.000', '38.000'], [$this->stockOf($zero), $this->stockOf($blank)]);
        $this->assertSame(0, $this->inStore(fn () => StockMovement::query()->count()));
    }

    public function test_an_arrival_can_be_typed_in_mixed_units(): void
    {
        $paint = $this->makeProduct('Cat', '24');
        $opname = $this->arrivalsOpname(StockOpnameStatus::Counting);
        $item = $this->addItem($opname, $paint, null, '24');

        $this->saveCount($opname, $item, [['unit_code' => 'DUS', 'quantity' => '2'], ['unit_code' => 'PAIL', 'quantity' => '3']]);
        $this->action('finish', $opname);
        $this->action('post', $opname);

        $this->assertSame('35.000', $this->stockOf($paint));
    }

    public function test_the_show_page_of_an_arrivals_opname_reports_what_arrived_not_the_difference(): void
    {
        $arrived = $this->makeProduct('Cat', '24', null, '2000');
        $untouched = $this->makeProduct('Kuas', '10');
        $opname = $this->arrivalsOpname(StockOpnameStatus::Review, ['scope' => StockOpnameScope::Selected]);
        $this->addItem($opname, $arrived, '5', '24');
        $this->addItem($opname, $untouched, '0', '10');

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]))->assertInertia(fn (Assert $page) => $page
            ->where('opname.is_counting_arrivals', true)
            ->where('summary.variance_count', 1)
            ->where('summary.variance_value', '10000.00')
            ->where('items.data.0.product.name', 'Cat')
            ->where('items.data.0.system_quantity', '24.000')
            ->where('items.data.0.counted_quantity', '5.000')
            ->where('items.data.0.variance', '5.000')
            ->where('items.data.0.variance_value', '10000.00')
            ->where('items.data.1.variance', '0.000'));

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id, 'filter' => 'variance']))
            ->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('items.data.0.product.name', 'Cat'));
    }

    public function test_only_the_stock_increase_reason_is_counting_arrivals(): void
    {
        foreach (StockOpnameReason::cases() as $reason) {
            $this->assertSame($reason === StockOpnameReason::StockIncrease, $reason->isArrivalCount(), $reason->value);
        }

        $opname = $this->opname(['reason_code' => StockOpnameReason::Routine]);

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]))
            ->assertInertia(fn (Assert $page) => $page->where('opname.is_counting_arrivals', false));
    }

    public function test_counting_cannot_be_finished_before_anything_is_counted(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Counting);
        $this->addItem($opname, $this->makeProduct('Cat'));

        $this->action('finish', $opname)->assertSessionHas('error', 'Belum ada barang yang dihitung.');

        $this->assertSame(StockOpnameStatus::Counting, $this->statusOf($opname));
    }

    public function test_a_finished_count_goes_to_review_and_can_be_reopened(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Counting);
        $this->addItem($opname, $this->makeProduct('Cat'), '5');

        $this->action('finish', $opname)->assertSessionHas('success');
        $this->assertSame(StockOpnameStatus::Review, $this->statusOf($opname));

        $this->action('reopen', $opname)->assertSessionHas('success');
        $this->assertSame(StockOpnameStatus::Counting, $this->statusOf($opname));
    }

    public function test_posting_writes_each_variance_to_the_stock_and_the_ledger(): void
    {
        $short = $this->makeProduct('Cat', '24');
        $over = $this->makeProduct('Kuas', '10');
        $same = $this->makeProduct('Paku', '8');
        $opname = $this->opnameInStatus(StockOpnameStatus::Review);
        $this->addItem($opname, $short, '23', '24');
        $this->addItem($opname, $over, '15', '10');
        $this->addItem($opname, $same, '8', '8');

        $this->action('post', $opname)->assertSessionHas('success');

        $this->assertSame('23.000', $this->stockOf($short));
        $this->assertSame('15.000', $this->stockOf($over));
        $this->assertSame('8.000', $this->stockOf($same));

        $movements = $this->inStore(fn () => StockMovement::query()->orderBy('id')->get());
        $this->assertCount(2, $movements);
        $this->assertSame([$short->id, '-1.000', '23.000'], [$movements[0]->product_id, $movements[0]->quantity, $movements[0]->balance_after]);
        $this->assertSame([$over->id, '5.000', '15.000'], [$movements[1]->product_id, $movements[1]->quantity, $movements[1]->balance_after]);
        $this->assertSame(StockMovementType::OpnameAdjustment, $movements[0]->type);
        $this->assertSame('stock_opname', $movements[0]->reference_type);
        $this->assertSame($opname->id, $movements[0]->reference_id);
        $this->assertSame($this->admin->id, $movements[0]->user_id);

        $posted = $this->inStore(fn () => $opname->fresh());
        $this->assertSame(StockOpnameStatus::Posted, $posted->status);
        $this->assertSame($this->admin->id, $posted->approved_by);
        $this->assertNotNull($posted->posted_at);
        $this->assertSame(['24.000', '-1.000'], [$this->item($opname, $short)->system_quantity_at_posting, $this->item($opname, $short)->variance]);
    }

    public function test_the_variance_is_taken_from_the_live_stock_not_from_the_snapshot(): void
    {
        $paint = $this->makeProduct('Cat', '24');
        $opname = $this->opnameInStatus(StockOpnameStatus::Review);
        $this->addItem($opname, $paint, '20', '24');
        $this->setStock($paint, '22');

        $this->action('post', $opname);

        $this->assertSame('20.000', $this->stockOf($paint));
        $this->assertSame('-2.000', $this->inStore(fn () => StockMovement::query()->firstOrFail()->quantity));
        $this->assertSame('22.000', $this->item($opname, $paint)->system_quantity_at_posting);
    }

    public function test_uncounted_items_are_left_alone_when_posting(): void
    {
        $untouched = $this->makeProduct('Cat', '9');
        $opname = $this->opnameInStatus(StockOpnameStatus::Review);
        $this->addItem($opname, $untouched, null, '9');
        $this->addItem($opname, $this->makeProduct('Kuas', '1'), '2', '1');

        $this->action('post', $opname);

        $this->assertSame('9.000', $this->stockOf($untouched));
        $this->assertNull($this->item($opname, $untouched)->variance);
        $this->assertSame(1, $this->inStore(fn () => StockMovement::query()->count()));
    }

    public function test_posting_writes_movements_in_product_order_whatever_order_the_items_were_added(): void
    {
        $first = $this->makeProduct('Cat', '5');
        $second = $this->makeProduct('Kuas', '5');
        $opname = $this->opnameInStatus(StockOpnameStatus::Review);
        $this->addItem($opname, $second, '6', '5');
        $this->addItem($opname, $first, '6', '5');

        $this->action('post', $opname);

        $this->assertSame(
            [$first->id, $second->id],
            $this->inStore(fn () => StockMovement::query()->orderBy('id')->pluck('product_id')->all()),
        );
    }

    /**
     * The review screen works the variance out in SQL and posting in PHP; they must agree for both kinds of count.
     */
    public function test_the_variance_on_the_review_screen_is_the_one_that_gets_posted(): void
    {
        foreach ([StockOpnameReason::Routine, StockOpnameReason::StockIncrease] as $reason) {
            $short = $this->makeProduct('Cat '.$reason->value, '24');
            $over = $this->makeProduct('Kuas '.$reason->value, '10');
            $opname = $this->opname(['status' => StockOpnameStatus::Review, 'reason_code' => $reason]);
            $this->addItem($opname, $short, '23', '24');
            $this->addItem($opname, $over, '15', '10');

            $onScreen = fn () => $this->inStore(fn () => app(StockOpnameReport::class)->paginateItems($opname->fresh(), null, null, 50)
                ->getCollection()->mapWithKeys(fn (StockOpnameItem $item): array => [$item->product_id => $item->getAttribute('variance_quantity')])->all());

            $beforePosting = $onScreen();
            $this->action('post', $opname)->assertSessionHas('success');
            $afterPosting = $onScreen();

            foreach ([$short, $over] as $product) {
                $posted = $this->item($opname, $product)->variance;

                $this->assertTrue(BigDecimal::of($beforePosting[$product->id])->isEqualTo($posted), "{$reason->value}: screen before posting differs from the posted variance");
                $this->assertTrue(BigDecimal::of($afterPosting[$product->id])->isEqualTo($posted), "{$reason->value}: screen after posting differs from the posted variance");
            }
        }
    }

    public function test_an_opening_balance_opname_writes_opening_balance_movements(): void
    {
        $paint = $this->makeProduct('Cat');
        $opname = $this->opname(['status' => StockOpnameStatus::Review, 'reason_code' => StockOpnameReason::OpeningBalance]);
        $this->addItem($opname, $paint, '30');

        $this->action('post', $opname);

        $movement = $this->inStore(fn () => StockMovement::query()->firstOrFail());
        $this->assertSame(StockMovementType::OpeningBalance, $movement->type);
        $this->assertSame('30.000', $movement->quantity);
        $this->assertSame('30.000', $this->stockOf($paint));
    }

    public function test_a_product_without_a_stock_row_gets_one_when_posted(): void
    {
        $paint = $this->inStore(fn () => Product::factory()->create(['base_unit_code' => Unit::factory()->create()->code]));
        $opname = $this->opnameInStatus(StockOpnameStatus::Review);
        $this->addItem($opname, $paint, '4');

        $this->action('post', $opname);

        $this->assertSame('4.000', $this->stockOf($paint));
    }

    public function test_an_opname_can_only_be_posted_once_and_only_from_review(): void
    {
        $paint = $this->makeProduct('Cat', '5');
        $opname = $this->opnameInStatus(StockOpnameStatus::Review);
        $this->addItem($opname, $paint, '8', '5');

        $this->action('post', $opname)->assertSessionHas('success');
        $this->action('post', $opname)->assertSessionHas('error');

        $this->assertSame('8.000', $this->stockOf($paint));
        $this->assertSame(1, $this->inStore(fn () => StockMovement::query()->count()));

        $counting = $this->opnameInStatus(StockOpnameStatus::Counting);
        $this->action('post', $counting)->assertSessionHas('error');
        $this->assertSame(StockOpnameStatus::Counting, $this->statusOf($counting));
    }

    public function test_cancelling_changes_no_stock_and_frees_the_products(): void
    {
        $paint = $this->makeProduct('Cat', '5');
        $opname = $this->opnameInStatus(StockOpnameStatus::Review, StockOpnameScope::Selected);
        $this->addItem($opname, $paint, '9', '5');

        $this->action('cancel', $opname)->assertSessionHas('success');

        $this->assertSame(StockOpnameStatus::Cancelled, $this->statusOf($opname));
        $this->assertNotNull($this->inStore(fn () => StockOpname::onlyTrashed()->find($opname->id)));
        $this->assertSame(1, $this->inStore(fn () => $opname->items()->count()));
        $this->assertSame('5.000', $this->stockOf($paint));
        $this->assertSame(0, $this->inStore(fn () => StockMovement::query()->count()));

        $this->action('cancel', $opname)->assertNotFound();
        $this->action('start', $opname)->assertNotFound();
    }

    public function test_cancelling_a_draft_deletes_it_and_its_items_and_goes_back_to_the_list(): void
    {
        $paint = $this->makeProduct('Cat', '5');
        $draft = $this->opnameInStatus(StockOpnameStatus::Draft, StockOpnameScope::Selected);
        $this->addItem($draft, $paint, null, '5');
        $kept = $this->opnameInStatus(StockOpnameStatus::Review, StockOpnameScope::Selected);

        $this->action('cancel', $draft)->assertRedirect($this->url('index'))->assertSessionHas('success', 'Draft opname dihapus.');

        $this->assertSame([$kept->id], $this->inStore(fn () => StockOpname::withTrashed()->pluck('id')->all()));
        $this->assertSame(0, $this->inStore(fn () => StockOpnameItem::query()->count()));
        $this->assertSame('5.000', $this->stockOf($paint));
        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $draft->id]))->assertNotFound();
    }

    public function test_a_deleted_drafts_number_is_not_used_again(): void
    {
        $this->createOpname();
        $first = $this->inStore(fn () => StockOpname::query()->firstOrFail());
        $this->action('cancel', $first);

        $this->createOpname();

        $this->assertSame(
            ['OPN/TOP/2610/0002'],
            $this->inStore(fn () => StockOpname::query()->pluck('number')->all()),
        );
    }

    public function test_cancelling_an_opname_still_counting_soft_deletes_it_and_frees_the_products(): void
    {
        $paint = $this->makeProduct('Cat', '5');
        $opname = $this->opnameInStatus(StockOpnameStatus::Counting, StockOpnameScope::Selected);
        $this->addItem($opname, $paint, '3', '5');

        $this->action('cancel', $opname)->assertRedirect($this->url('index'))->assertSessionHas('success', 'Opname dibatalkan.');

        $this->assertSame(0, $this->inStore(fn () => StockOpname::query()->count()));
        $this->assertSame(1, $this->inStore(fn () => StockOpname::onlyTrashed()->where('status', StockOpnameStatus::Cancelled)->count()));
        $this->assertSame(1, $this->inStore(fn () => StockOpnameItem::query()->count()));
        $this->assertSame('5.000', $this->stockOf($paint));

        $next = $this->opnameInStatus(StockOpnameStatus::Draft, StockOpnameScope::Selected);
        $this->actingAs($this->admin)->post($this->url('items.store', ['stock_opname' => $next->id]), ['product_id' => $paint->id])->assertSessionHas('success');
    }

    public function test_only_a_cancelled_draft_is_erased_for_good(): void
    {
        $this->assertTrue(StockOpnameStatus::Draft->isErasedWhenCancelled());

        foreach ([StockOpnameStatus::Counting, StockOpnameStatus::Review, StockOpnameStatus::Posted, StockOpnameStatus::Cancelled] as $status) {
            $this->assertFalse($status->isErasedWhenCancelled(), $status->value);
        }
    }

    public function test_cancelled_opnames_are_hidden_from_the_list_until_the_cancelled_filter_is_chosen(): void
    {
        $this->opname(['status' => StockOpnameStatus::Posted, 'number' => 'OPN/TOP/2610/0001']);
        $cancelled = $this->opnameInStatus(StockOpnameStatus::Counting, StockOpnameScope::Selected);
        $this->action('cancel', $cancelled);

        $this->actingAs($this->admin)->get($this->url('index'))
            ->assertInertia(fn (Assert $page) => $page->has('opnames.data', 1)->where('opnames.data.0.status', 'posted'));
        $this->actingAs($this->admin)->get($this->url('index', ['status' => 'cancelled']))
            ->assertInertia(fn (Assert $page) => $page->has('opnames.data', 1)->where('opnames.data.0.status', 'cancelled'));
    }

    public function test_a_cancelled_opname_can_still_be_opened_but_not_changed(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Counting, StockOpnameScope::Selected);
        $this->addItem($opname, $this->makeProduct('Cat'), '3');
        $this->action('cancel', $opname);

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('opname.status', 'cancelled')
            ->where('opname.can_cancel', false)
            ->where('opname.can_count', false)
            ->has('items.data', 1));
    }

    public function test_a_posted_opname_cannot_be_cancelled(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Posted);

        $this->action('cancel', $opname)->assertSessionHas('error');

        $this->assertSame(StockOpnameStatus::Posted, $this->statusOf($opname));
    }

    public function test_the_show_page_gives_items_with_system_quantity_variance_and_estimated_value(): void
    {
        $short = $this->makeProduct('Cat', '24', null, '2000');
        $uncounted = $this->makeProduct('Kuas', '38');
        $opname = $this->opnameInStatus(StockOpnameStatus::Counting, StockOpnameScope::Category);
        $this->addItem($opname, $short, '22', '24');
        $this->addItem($opname, $uncounted, null, '38');

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tenant/StockOpnames/Show')
            ->where('opname.number', $opname->number)
            ->where('opname.status', 'counting')
            ->where('opname.can_count', true)
            ->where('opname.can_post', false)
            ->where('opname.can_manage_products', false)
            ->where('summary.items_count', 2)
            ->where('summary.counted_count', 1)
            ->where('summary.variance_count', 1)
            ->where('summary.variance_value', '-4000.00')
            ->has('items.data', 2)
            ->where('items.data.0.product.name', 'Cat')
            ->where('items.data.0.system_quantity', '24.000')
            ->where('items.data.0.counted_quantity', '22.000')
            ->where('items.data.0.variance', '-2.000')
            ->where('items.data.0.variance_value', '-4000.00')
            ->where('items.data.0.base_unit', 'PAIL')
            ->has('items.data.0.units', 2)
            ->where('items.data.1.counted_quantity', null)
            ->where('items.data.1.variance', null)
        );
    }

    public function test_a_posted_opname_shows_the_system_quantity_it_was_posted_against(): void
    {
        $paint = $this->makeProduct('Cat', '24');
        $opname = $this->opnameInStatus(StockOpnameStatus::Review);
        $this->addItem($opname, $paint, '20', '24');
        $this->action('post', $opname);
        $this->setStock($paint, '99');

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]))->assertInertia(fn (Assert $page) => $page
            ->where('items.data.0.system_quantity', '24.000')
            ->where('items.data.0.variance', '-4.000')
            ->where('summary.variance_count', 1)
        );
    }

    public function test_the_items_can_be_searched_and_filtered(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Counting);
        $this->addItem($opname, $this->makeProduct('Cat Tembok', '10'), '10', '10');
        $this->addItem($opname, $this->makeProduct('Cat Kayu', '10'), '7', '10');
        $this->addItem($opname, $this->makeProduct('Kuas', '10'));
        $url = fn (array $query) => $this->url('show', ['stock_opname' => $opname->id, ...$query]);

        $this->actingAs($this->admin);
        $this->get($url(['search' => 'cat']))->assertInertia(fn (Assert $page) => $page->has('items.data', 2));
        $this->get($url(['filter' => 'uncounted']))->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('items.data.0.product.name', 'Kuas'));
        $this->get($url(['filter' => 'variance']))->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('items.data.0.product.name', 'Cat Kayu'));
    }

    public function test_product_search_finds_products_by_name_or_sku(): void
    {
        $this->makeProduct('Cat Tembok');
        $this->makeProduct('Kuas');

        $this->actingAs($this->admin)
            ->getJson(route('tenant.products.search', ['tenant' => $this->store->code, 'q' => 'tembok']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Cat Tembok')
            ->assertJsonPath('data.0.base_unit', 'PAIL');

        $this->actingAs($this->userWithRole(UserRole::Cashier))
            ->getJson(route('tenant.products.search', ['tenant' => $this->store->code, 'q' => 'x']))
            ->assertForbidden();
    }

    /**
     * The SQL (lower case) that runs while the action does.
     *
     * @return list<string>
     */
    private function statementsDuring(callable $action): array
    {
        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = strtolower($query->sql);
        });

        $action();

        return $statements;
    }

    /**
     * @param  list<string>  $statements
     * @param  list<string>  $needles  every one must be in the statement
     */
    private function firstStatementWith(array $statements, array $needles): ?int
    {
        foreach ($statements as $index => $statement) {
            if (collect($needles)->every(fn (string $needle): bool => str_contains($statement, $needle))) {
                return $index;
            }
        }

        return null;
    }

    public function test_adding_a_product_locks_the_warehouse_before_checking_whether_another_opname_holds_it(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Draft);
        $paint = $this->makeProduct('Cat');

        $statements = $this->statementsDuring(fn () => $this->actingAs($this->admin)->post($this->url('items.store', ['stock_opname' => $opname->id]), ['product_id' => $paint->id])->assertSessionHas('success'));

        $lockAt = $this->firstStatementWith($statements, ['from `warehouses`', 'for update']);
        $busyCheckAt = $this->firstStatementWith($statements, ['from `stock_opname_items`', 'join `stock_opnames`']);

        $this->assertNotNull($lockAt);
        $this->assertNotNull($busyCheckAt);
        $this->assertLessThan($busyCheckAt, $lockAt);
    }

    public function test_starting_a_category_opname_locks_the_warehouse_before_choosing_its_products(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Draft, StockOpnameScope::Category);
        $this->makeProduct('Cat');

        $statements = $this->statementsDuring(fn () => $this->action('start', $opname)->assertSessionHas('success'));

        $lockAt = $this->firstStatementWith($statements, ['from `warehouses`', 'for update']);
        $busyCheckAt = $this->firstStatementWith($statements, ['from `stock_opname_items`', 'join `stock_opnames`']);

        $this->assertNotNull($lockAt);
        $this->assertNotNull($busyCheckAt);
        $this->assertLessThan($busyCheckAt, $lockAt);
    }

    public function test_posting_locks_the_stock_rows_once_however_many_items_there_are(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Review);
        $products = collect(range(1, 6))->map(fn (int $number): Product => $this->makeProduct("Cat {$number}", '10'));
        $products->each(fn (Product $product) => $this->addItem($opname, $product, '12'));

        $statements = $this->statementsDuring(fn () => $this->action('post', $opname)->assertSessionHas('success'));

        $this->assertSame(1, count(array_filter($statements, fn (string $statement): bool => str_contains($statement, 'from `stocks`') && str_contains($statement, 'for update'))));
        $products->each(fn (Product $product) => $this->assertSame('12.000', $this->stockOf($product)));
    }

    public function test_posting_creates_the_stock_row_of_a_product_that_has_none(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Review);
        $withoutStock = $this->makeProduct('Cat Baru');
        $this->inStore(fn () => Stock::query()->where('product_id', $withoutStock->id)->delete());
        $this->addItem($opname, $withoutStock, '7');

        $this->action('post', $opname)->assertSessionHas('success');

        $this->assertSame('7.000', $this->stockOf($withoutStock));
        $this->assertSame('0.000', $this->item($opname, $withoutStock)->system_quantity_at_posting);
    }

    public function test_a_count_too_big_for_the_stock_column_is_refused_with_a_message(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Counting);
        $paint = $this->makeProduct('Cat');
        $pallet = $this->inStore(fn () => Unit::query()->firstOrCreate(['code' => 'PALET'], ['name' => 'Palet']));
        $this->inStore(fn () => ProductUnit::factory()->create(['product_id' => $paint->id, 'unit_code' => $pallet->code, 'conversion_factor' => 5000000, 'is_base' => false, 'is_default_sale' => false, 'sort_order' => 2]));
        $item = $this->addItem($opname, $paint);

        $this->saveCount($opname, $item, [['unit_code' => 'PALET', 'quantity' => '999999999']])
            ->assertSessionHas('error', 'Cat: jumlah yang dihitung terlalu besar setelah diubah ke satuan terkecil. Periksa angkanya.');

        $this->assertNull($this->item($opname, $paint)->counted_quantity);
    }

    public function test_the_value_of_a_posted_opname_does_not_move_when_the_cost_price_is_edited_later(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Review);
        $paint = $this->makeProduct('Cat', '10', cost: '1000');
        $this->addItem($opname, $paint, '12');
        $this->action('post', $opname)->assertSessionHas('success');

        $this->inStore(fn () => $paint->forceFill(['cost_price' => 5000])->save());

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]))->assertInertia(fn (Assert $page) => $page
            ->where('summary.variance_value', '2000.00')
            ->where('items.data.0.variance_value', '2000.00'));
        $this->assertSame('1000.00', $this->item($opname, $paint)->cost_price_at_posting);
    }

    public function test_the_value_of_an_opname_still_being_counted_follows_the_current_cost_price(): void
    {
        $opname = $this->opnameInStatus(StockOpnameStatus::Review);
        $paint = $this->makeProduct('Cat', '10', cost: '1000');
        $this->addItem($opname, $paint, '12');

        $this->inStore(fn () => $paint->forceFill(['cost_price' => 5000])->save());

        $this->actingAs($this->admin)->get($this->url('show', ['stock_opname' => $opname->id]))->assertInertia(fn (Assert $page) => $page->where('summary.variance_value', '10000.00'));
    }
}

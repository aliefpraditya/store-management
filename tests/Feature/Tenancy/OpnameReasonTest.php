<?php

namespace Tests\Feature\Tenancy;

use App\Enums\OpnameReasonBehavior;
use App\Enums\UserRole;
use App\Models\OpnameReason;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsCashierFixtures;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

class OpnameReasonTest extends TestCase
{
    use BuildsCashierFixtures, PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_opname_reasons';

    private Store $store;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_opname_reasons', 'receipt_prefix' => 'TOS']);
        $this->admin = $this->storeUser(UserRole::StoreAdmin);
    }

    private function reason(string $name): OpnameReason
    {
        return $this->store->run(fn (): OpnameReason => OpnameReason::query()->where('name', $name)->firstOrFail());
    }

    private function storeUrl(): string
    {
        return route('tenant.opname-reasons.store', ['tenant' => $this->store->code]);
    }

    private function updateUrl(OpnameReason $reason): string
    {
        return route('tenant.opname-reasons.update', ['tenant' => $this->store->code, 'opname_reason' => $reason->id]);
    }

    public function test_the_settings_page_lists_the_reasons_with_where_to_change_them(): void
    {
        $this->actingAs($this->admin)->get(route('tenant.settings.index', ['tenant' => $this->store->code]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('opname_reasons', 4)
            ->where('opname_reasons.0.name', 'Pembelian')
            ->where('opname_reasons.0.is_system', true)
            ->where('opname_reasons.0.is_active', true)
            ->where('opname_reasons.3.name', 'Rusak')
            ->where('opname_reasons.3.update_url', $this->updateUrl($this->reason('Rusak')))
            ->where('urls.opname_reason_create', $this->storeUrl()));
    }

    public function test_an_admin_adds_a_reason_that_behaves_plainly(): void
    {
        $this->actingAs($this->admin)->post($this->storeUrl(), ['name' => '  Kadaluarsa '])->assertSessionHasNoErrors()->assertSessionHas('success', 'Alasan ditambahkan.');

        $reason = $this->reason('Kadaluarsa');
        $this->assertSame([OpnameReasonBehavior::Plain, false, true], [$reason->behavior, $reason->is_system, $reason->is_active]);
    }

    public function test_a_reason_is_renamed_and_switched_off_and_on(): void
    {
        $custom = $this->store->run(fn (): OpnameReason => OpnameReason::factory()->create(['name' => 'Tumpah']));

        $this->actingAs($this->admin)->put($this->updateUrl($custom), ['name' => 'Tumpah di gudang', 'is_active' => false])->assertSessionHasNoErrors()->assertSessionHas('success', 'Alasan disimpan.');
        $this->assertSame(['Tumpah di gudang', false], $this->store->run(fn (): array => [$custom->fresh()->name, $custom->fresh()->is_active]));

        $this->actingAs($this->admin)->put($this->updateUrl($custom), ['name' => 'Tumpah di gudang', 'is_active' => true])->assertSessionHasNoErrors();
        $this->assertTrue($this->store->run(fn (): bool => $custom->fresh()->is_active));
    }

    public function test_a_store_reason_can_be_renamed_but_not_switched_off_and_keeps_its_behavior(): void
    {
        $damaged = $this->reason('Rusak');

        $this->actingAs($this->admin)->put($this->updateUrl($damaged), ['name' => 'Rusak', 'is_active' => false])->assertSessionHasErrors(['is_active' => 'Alasan Rusak bawaan toko dan tidak bisa dinonaktifkan.']);
        $this->actingAs($this->admin)->put($this->updateUrl($damaged), ['name' => 'Barang rusak', 'behavior' => 'plain'])->assertSessionHasNoErrors();

        $damaged = $damaged->fresh();
        $this->assertSame(['Barang rusak', OpnameReasonBehavior::Damaged, true], $this->store->run(fn (): array => [$damaged->name, $damaged->behavior, $damaged->is_active]));
    }

    public function test_the_name_is_required_and_unique(): void
    {
        $this->actingAs($this->admin)->post($this->storeUrl(), ['name' => ''])->assertSessionHasErrors(['name' => 'Isi nama alasan.']);
        $this->actingAs($this->admin)->post($this->storeUrl(), ['name' => 'Hilang'])->assertSessionHasErrors(['name' => 'Alasan dengan nama itu sudah ada.']);
        $this->actingAs($this->admin)->put($this->updateUrl($this->reason('Cek stok')), ['name' => 'Hilang'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->put($this->updateUrl($this->reason('Cek stok')), ['name' => 'Cek stok'])->assertSessionHasNoErrors();
    }

    public function test_only_admins_manage_the_reasons(): void
    {
        $cashier = $this->storeUser();

        $this->actingAs($cashier)->post($this->storeUrl(), ['name' => 'Baru'])->assertForbidden();
        $this->actingAs($cashier)->put($this->updateUrl($this->reason('Hilang')), ['name' => 'Baru'])->assertForbidden();
        $this->assertSame(4, $this->store->run(fn (): int => OpnameReason::query()->count()));
    }
}

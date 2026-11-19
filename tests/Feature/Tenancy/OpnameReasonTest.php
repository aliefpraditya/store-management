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

/**
 * The reasons of an opname variance, kept on the store settings page and saved with its one Save.
 */
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

    /**
     * The row of a reason as the page holds it.
     *
     * @return array{id: int|null, name: string, is_active: bool}
     */
    private function row(?OpnameReason $reason, string $name, bool $isActive = true): array
    {
        return ['id' => $reason?->id, 'name' => $name, 'is_active' => $isActive];
    }

    /**
     * Saves the page with the given reason rows (the required settings go along).
     *
     * @param  list<array{id: int|null, name: string, is_active: bool}>  $rows
     */
    private function save(array $rows, ?User $by = null)
    {
        return $this->actingAs($by ?? $this->admin)->put(route('tenant.settings.update', ['tenant' => $this->store->code]), [
            'paper_width' => 80,
            'is_qz_tray_enabled' => false,
            'lock_sales_during_opname' => false,
            'auto_print_receipt' => false,
            'opname_reasons' => $rows,
        ]);
    }

    public function test_the_settings_page_lists_the_reasons(): void
    {
        $this->actingAs($this->admin)->get(route('tenant.settings.index', ['tenant' => $this->store->code]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('opname_reasons', 4)
            ->where('opname_reasons.0.name', 'Pembelian')
            ->where('opname_reasons.0.is_system', true)
            ->where('opname_reasons.0.is_active', true)
            ->where('opname_reasons.3.name', 'Rusak')
            ->missing('opname_reasons.3.update_url')
            ->missing('urls.opname_reason_create'));
    }

    public function test_an_admin_adds_a_reason_that_behaves_plainly(): void
    {
        $this->save([$this->row(null, '  Kadaluarsa ')])->assertSessionHasNoErrors()->assertSessionHas('success', 'Pengaturan disimpan.');

        $reason = $this->reason('Kadaluarsa');
        $this->assertSame([OpnameReasonBehavior::Plain, false, true], [$reason->behavior, $reason->is_system, $reason->is_active]);
    }

    public function test_a_reason_is_renamed_and_switched_off_and_on(): void
    {
        $custom = $this->store->run(fn (): OpnameReason => OpnameReason::factory()->create(['name' => 'Tumpah']));

        $this->save([$this->row($custom, 'Tumpah di gudang', false)])->assertSessionHasNoErrors();
        $this->assertSame(['Tumpah di gudang', false], $this->store->run(fn (): array => [$custom->fresh()->name, $custom->fresh()->is_active]));

        $this->save([$this->row($custom, 'Tumpah di gudang')])->assertSessionHasNoErrors();
        $this->assertTrue($this->store->run(fn (): bool => $custom->fresh()->is_active));
    }

    public function test_a_new_reason_can_be_added_already_switched_off(): void
    {
        $this->save([$this->row(null, 'Sampel', false)])->assertSessionHasNoErrors();

        $this->assertFalse($this->reason('Sampel')->is_active);
    }

    public function test_a_store_reason_can_be_renamed_but_not_switched_off_and_keeps_its_behavior(): void
    {
        $damaged = $this->reason('Rusak');

        $this->save([$this->row($damaged, 'Rusak', false)])->assertSessionHasErrors(['opname_reasons.0.is_active' => 'Alasan Rusak bawaan toko dan tidak bisa dinonaktifkan.']);
        $this->save([['id' => $damaged->id, 'name' => 'Barang rusak', 'is_active' => true, 'behavior' => 'plain']])->assertSessionHasNoErrors();

        $damaged = $damaged->fresh();
        $this->assertSame(['Barang rusak', OpnameReasonBehavior::Damaged, true], $this->store->run(fn (): array => [$damaged->name, $damaged->behavior, $damaged->is_active]));
    }

    public function test_the_name_is_required_and_unique(): void
    {
        $this->save([$this->row(null, '')])->assertSessionHasErrors(['opname_reasons.0.name' => 'Isi nama alasan.']);
        $this->save([$this->row(null, 'Hilang')])->assertSessionHasErrors(['opname_reasons.0.name' => 'Alasan dengan nama itu sudah ada.']);
        $this->save([$this->row($this->reason('Cek stok'), 'Hilang')])->assertSessionHasErrors('opname_reasons.0.name');
        $this->save([$this->row(null, 'Baru'), $this->row(null, 'baru')])->assertSessionHasErrors('opname_reasons.1.name');
        $this->save([$this->row($this->reason('Cek stok'), 'Cek stok')])->assertSessionHasNoErrors();

        $this->assertSame(4, $this->store->run(fn (): int => OpnameReason::query()->count()));
    }

    public function test_only_admins_manage_the_reasons(): void
    {
        $this->save([$this->row(null, 'Baru')], $this->storeUser())->assertForbidden();

        $this->assertSame(4, $this->store->run(fn (): int => OpnameReason::query()->count()));
    }
}

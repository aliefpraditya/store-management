<?php

namespace Tests\Feature\Tenancy;

use App\Models\Register;
use App\Models\Store;
use App\Services\DocumentNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparesStoreDatabase;
use Tests\TestCase;

/**
 * Receipt, return, delivery note and opname numbers: type, store prefix, register (receipts only), year-month and a
 * running number that restarts every month.
 */
class DocumentNumberGeneratorTest extends TestCase
{
    use PreparesStoreDatabase, RefreshDatabase;

    private const STORE_DATABASE = 'store_test_document_numbers';

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->prepareStoreDatabase(['code' => 'test_document_numbers', 'receipt_prefix' => 'TDN']);
    }

    /**
     * @param  callable(DocumentNumberGenerator): list<string>  $callback
     * @return list<string>
     */
    private function numbers(callable $callback): array
    {
        return $this->store->run(fn (): array => DB::transaction(fn (): array => $callback(app(DocumentNumberGenerator::class))));
    }

    public function test_the_running_number_goes_on_within_a_month_and_starts_again_in_the_next(): void
    {
        $numbers = $this->numbers(fn (DocumentNumberGenerator $generator): array => [
            $generator->next('OPN', Carbon::parse('2026-10-15')),
            $generator->next('OPN', Carbon::parse('2026-10-20')),
            $generator->next('OPN', Carbon::parse('2026-11-01')),
            $generator->next('OPN', Carbon::parse('2026-11-02')),
        ]);

        $this->assertSame(['OPN/TDN/2610/0001', 'OPN/TDN/2610/0002', 'OPN/TDN/2611/0001', 'OPN/TDN/2611/0002'], $numbers);
    }

    public function test_each_document_type_counts_on_its_own(): void
    {
        $numbers = $this->numbers(fn (DocumentNumberGenerator $generator): array => [
            $generator->next('OPN', Carbon::parse('2026-10-15')),
            $generator->next('RET', Carbon::parse('2026-10-15')),
            $generator->next('OPN', Carbon::parse('2026-10-15')),
        ]);

        $this->assertSame(['OPN/TDN/2610/0001', 'RET/TDN/2610/0001', 'OPN/TDN/2610/0002'], $numbers);
    }

    public function test_receipts_are_numbered_per_register(): void
    {
        $register = $this->store->run(fn () => Register::query()->where('code', 'K1')->firstOrFail());
        $secondRegister = $this->store->run(fn () => Register::factory()->create(['code' => 'K2', 'name' => 'Kasir 2', 'warehouse_id' => $register->warehouse_id]));

        $numbers = $this->numbers(fn (DocumentNumberGenerator $generator): array => [
            $generator->next('INV', Carbon::parse('2026-10-15'), $register),
            $generator->next('INV', Carbon::parse('2026-10-15'), $secondRegister),
            $generator->next('INV', Carbon::parse('2026-10-15'), $register),
        ]);

        $this->assertSame(['INV/TDN/K1/2610/0001', 'INV/TDN/K2/2610/0001', 'INV/TDN/K1/2610/0002'], $numbers);
    }

    public function test_a_number_that_was_rolled_back_is_handed_out_again(): void
    {
        $this->store->run(function (): void {
            DB::beginTransaction();
            app(DocumentNumberGenerator::class)->next('OPN', Carbon::parse('2026-10-15'));
            DB::rollBack();
        });

        $numbers = $this->numbers(fn (DocumentNumberGenerator $generator): array => [$generator->next('OPN', Carbon::parse('2026-10-15'))]);

        $this->assertSame(['OPN/TDN/2610/0001'], $numbers);
    }
}

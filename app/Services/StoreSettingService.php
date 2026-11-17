<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreSetting;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * The settings an admin keeps for one store: how its paper (receipt, delivery note, reports) reads, and two rules of
 * the stock opname. Each has a default that holds until it is changed, and a blank text goes back to the default. Must
 * run while the store's tenancy is initialized.
 *
 * @phpstan-type Letterhead array{name: string, address: string|null, phone: string|null}
 * @phpstan-type StoreSettings array{receipt_name: string, receipt_address: string, receipt_phone: string, receipt_footer: string, paper_width: int, large_variance_threshold: string, lock_sales_during_opname: bool, auto_print_receipt: bool}
 */
class StoreSettingService
{
    public const DEFAULT_RECEIPT_FOOTER = "Barang yang sudah dibeli tidak dapat ditukar atau dikembalikan kecuali ada perjanjian sebelumnya.\nTerimakasih Telah Berbelanja";

    /** The widths of the thermal roll a receipt can be printed on, in millimetres. */
    public const PAPER_WIDTHS = [58, 80];

    public const DEFAULT_PAPER_WIDTH = 80;

    private const KEYS = ['receipt_name', 'receipt_address', 'receipt_phone', 'receipt_footer', 'paper_width', 'large_variance_threshold', 'lock_sales_during_opname', 'auto_print_receipt'];

    /** @var array<string, string|null>|null what the database held when it was first asked, until the next update */
    private ?array $storedSettings = null;

    /**
     * The store's name, address and phone as printed on paper: what was set for the paper, else what the store has.
     *
     * @return Letterhead
     */
    public function letterhead(): array
    {
        /** @var Store $store */
        $store = tenant();
        $stored = $this->stored();

        return [
            'name' => filled($stored['receipt_name'] ?? null) ? $stored['receipt_name'] : $store->name,
            'address' => filled($stored['receipt_address'] ?? null) ? $stored['receipt_address'] : $store->address,
            'phone' => filled($stored['receipt_phone'] ?? null) ? $stored['receipt_phone'] : $store->phone,
        ];
    }

    /**
     * The text that closes a receipt, one line per line break.
     */
    public function receiptFooter(): string
    {
        $footer = $this->stored()['receipt_footer'] ?? null;

        return filled($footer) ? $footer : self::DEFAULT_RECEIPT_FOOTER;
    }

    /**
     * @return int one of {@see self::PAPER_WIDTHS}
     */
    public function paperWidth(): int
    {
        $width = (int) ($this->stored()['paper_width'] ?? self::DEFAULT_PAPER_WIDTH);

        return in_array($width, self::PAPER_WIDTHS, true) ? $width : self::DEFAULT_PAPER_WIDTH;
    }

    /**
     * What an opname variance is worth (in rupiah, at the last cost price) before it needs a reason; null when
     * there is no such limit.
     */
    public function largeVarianceThreshold(): ?BigDecimal
    {
        $threshold = $this->stored()['large_variance_threshold'] ?? null;

        return filled($threshold) && BigDecimal::of($threshold)->isPositive() ? BigDecimal::of($threshold) : null;
    }

    public function isSalesLockedDuringOpname(): bool
    {
        return ($this->stored()['lock_sales_during_opname'] ?? '0') === '1';
    }

    /**
     * Whether the till prints the receipt by itself once a sale is paid, so the cashier does not press print each time.
     */
    public function isReceiptPrintedAutomatically(): bool
    {
        return ($this->stored()['auto_print_receipt'] ?? '0') === '1';
    }

    /**
     * Every setting as the settings page shows it.
     *
     * @return StoreSettings
     */
    public function all(): array
    {
        $stored = $this->stored();

        return [
            'receipt_name' => (string) ($stored['receipt_name'] ?? ''),
            'receipt_address' => (string) ($stored['receipt_address'] ?? ''),
            'receipt_phone' => (string) ($stored['receipt_phone'] ?? ''),
            'receipt_footer' => $this->receiptFooter(),
            'paper_width' => $this->paperWidth(),
            'large_variance_threshold' => (string) ($this->largeVarianceThreshold()?->toScale(0, RoundingMode::HalfUp) ?? ''),
            'lock_sales_during_opname' => $this->isSalesLockedDuringOpname(),
            'auto_print_receipt' => $this->isReceiptPrintedAutomatically(),
        ];
    }

    /**
     * Saves the settings. A blank text is stored as nothing, so it falls back to its default.
     *
     * The rows that do not exist yet are inserted first with `insertOrIgnore`: two first saves at the same moment would
     * otherwise both try to create the same key and one of them would fail on the unique key. The values are then
     * written as updates, so the audit trail keeps recording each change.
     *
     * @param  array{receipt_name?: string|null, receipt_address?: string|null, receipt_phone?: string|null, receipt_footer?: string|null, paper_width: int|string, large_variance_threshold?: string|int|float|null, lock_sales_during_opname: bool, auto_print_receipt: bool}  $settings
     */
    public function update(array $settings): void
    {
        DB::transaction(function () use ($settings): void {
            StoreSetting::query()->insertOrIgnore(array_map(
                fn (string $key): array => ['key' => $key, 'value' => null, 'created_at' => now(), 'updated_at' => now()],
                self::KEYS,
            ));

            foreach (self::KEYS as $key) {
                $value = $settings[$key] ?? null;
                $value = is_bool($value) ? ($value ? '1' : '0') : (filled($value) ? trim((string) $value) : null);

                StoreSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });

        $this->storedSettings = null;
    }

    /**
     * Read once per instance: a receipt or a till search asks for several settings in a row.
     *
     * @return array<string, string|null>
     */
    private function stored(): array
    {
        return $this->storedSettings ??= StoreSetting::query()->whereIn('key', self::KEYS)->pluck('value', 'key')->all();
    }
}

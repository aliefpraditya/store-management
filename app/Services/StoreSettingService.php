<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreSetting;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * The settings an admin keeps for one store: how its paper (receipt, delivery note, reports) reads, which printers print
 * the receipt and the delivery note, two rules of the stock opname, and the step selling prices worked out from a margin
 * or a percent are rounded up to. Each has a default that holds until it is changed, and a blank text goes back to the default. Must run while the store's tenancy is initialized.
 *
 * @phpstan-type Letterhead array{name: string, address: string|null, phone: string|null}
 * @phpstan-type PrintTarget array{name: string, width_mm: float, height_mm: float|null}
 * @phpstan-type StoreSettings array{receipt_name: string, receipt_address: string, receipt_phone: string, receipt_footer: string, paper_width: int, receipt_printer: string, delivery_note_printer: string, is_qz_tray_enabled: bool, large_variance_threshold: string, lock_sales_during_opname: bool, auto_print_receipt: bool, selling_price_step: int}
 */
class StoreSettingService
{
    public const DEFAULT_RECEIPT_FOOTER = "Barang yang sudah dibeli tidak dapat ditukar atau dikembalikan kecuali ada perjanjian sebelumnya.\nTerimakasih Telah Berbelanja";

    /** The widths of the roll a receipt can be printed on, in millimetres (58 and 80 thermal, 76 for a dot matrix receipt printer such as the Epson TM-U220B). */
    public const PAPER_WIDTHS = [58, 76, 80];

    public const DEFAULT_PAPER_WIDTH = 80;

    /** The continuous form a delivery note is printed on (9.5 x 5.5 inch), in millimetres. */
    private const DELIVERY_NOTE_PAGE_MM = ['width' => 241.3, 'height' => 139.7];

    /** Rupiah. 0 is no rounding: a store rounds selling prices only once it sets a step of its own. */
    public const DEFAULT_SELLING_PRICE_STEP = 0;

    public const MAX_SELLING_PRICE_STEP = 1000000;

    private const KEYS = ['receipt_name', 'receipt_address', 'receipt_phone', 'receipt_footer', 'paper_width', 'receipt_printer', 'delivery_note_printer', 'is_qz_tray_enabled', 'large_variance_threshold', 'lock_sales_during_opname', 'auto_print_receipt', 'selling_price_step'];

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
     * Where the receipt (and the other roll prints: return, order, shift report) is printed: the printer named for it and
     * the roll it is loaded with. Null while no printer is named.
     *
     * @return PrintTarget|null
     */
    public function receiptPrintTarget(): ?array
    {
        $name = $this->printerNamed('receipt_printer');

        return $name === null ? null : ['name' => $name, 'width_mm' => (float) $this->paperWidth(), 'height_mm' => null];
    }

    /**
     * Where the delivery note is printed: the printer named for it, loaded with continuous form. Null while no printer is named.
     *
     * @return PrintTarget|null
     */
    public function deliveryNotePrintTarget(): ?array
    {
        $name = $this->printerNamed('delivery_note_printer');

        return $name === null ? null : ['name' => $name, 'width_mm' => self::DELIVERY_NOTE_PAGE_MM['width'], 'height_mm' => self::DELIVERY_NOTE_PAGE_MM['height']];
    }

    /**
     * Whether prints go straight to the named printers through QZ Tray, a small program on the cashier's computer, instead
     * of opening the browser's print dialog.
     */
    public function isQzTrayEnabled(): bool
    {
        return ($this->stored()['is_qz_tray_enabled'] ?? '0') === '1';
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
     * The multiple, in rupiah, that a selling price worked out from a rule is rounded up to: a margin typed on the item
     * form, and a percent or margin rule of a bulk price change. It also rounds up the subtotal of a receipt or order
     * line sold in a decimal quantity (see {@see CartPricer}). 0 means no rounding, which is also what a blank setting
     * gives.
     */
    public function sellingPriceStep(): int
    {
        $step = $this->stored()['selling_price_step'] ?? null;

        return filled($step) ? (int) $step : self::DEFAULT_SELLING_PRICE_STEP;
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
            'receipt_printer' => (string) ($stored['receipt_printer'] ?? ''),
            'delivery_note_printer' => (string) ($stored['delivery_note_printer'] ?? ''),
            'is_qz_tray_enabled' => $this->isQzTrayEnabled(),
            'large_variance_threshold' => (string) ($this->largeVarianceThreshold()?->toScale(0, RoundingMode::HalfUp) ?? ''),
            'lock_sales_during_opname' => $this->isSalesLockedDuringOpname(),
            'auto_print_receipt' => $this->isReceiptPrintedAutomatically(),
            'selling_price_step' => $this->sellingPriceStep(),
        ];
    }

    /**
     * Saves the settings. A blank text is stored as nothing, so it falls back to its default.
     *
     * The rows that do not exist yet are inserted first with `insertOrIgnore`: two first saves at the same moment would
     * otherwise both try to create the same key and one of them would fail on the unique key. The values are then
     * written as updates, so the audit trail keeps recording each change.
     *
     * @param  array{receipt_name?: string|null, receipt_address?: string|null, receipt_phone?: string|null, receipt_footer?: string|null, paper_width: int|string, receipt_printer?: string|null, delivery_note_printer?: string|null, is_qz_tray_enabled?: bool, large_variance_threshold?: string|int|float|null, lock_sales_during_opname: bool, auto_print_receipt: bool, selling_price_step?: string|int|null}  $settings
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

    private function printerNamed(string $key): ?string
    {
        $name = $this->stored()[$key] ?? null;

        return filled($name) ? $name : null;
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

<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copies the Mitra Utama items of the old POS database (the `legacy` connection) into the current store: items,
 * brands, categories (the old `jenis`), every unit (`satuans`) and prices. Stock, sales and history are not imported (docs 7.11).
 *
 * How the old data maps:
 * - Only items of owner 1 (Mitra Utama) are taken; Cahaya Baru is ignored.
 * - The unit with a purchase (`beli`) price is the base unit and its price is the cost; every item has one.
 * - Other units come from selling (`jual`) rows. Their `konversi` is how many base units one holds, but the old
 *   system left it at 1 almost everywhere, so a 1 is only believed when the unit name says so ("50 METER" over
 *   METER, LUSIN = 12). Any other unit is left out and listed in the review rows, because a wrong factor would
 *   take the wrong quantity off the stock.
 * - A blank brand becomes no brand. A barcode of 0, a short one, or one already used is dropped.
 * - The SKU is the old code without spaces ("MU 2409" -> "MU2409"); when that clashes with another code the
 *   spaces become dashes.
 *
 * Safe to run again: items whose SKU already exists are skipped.
 *
 * @phpstan-type ReviewRow array{sku: string, name: string, subject: string, value: string, reason: string}
 * @phpstan-type ImportResult array{created: int, existing: int, extra_units: int, new_units: int, review: list<ReviewRow>}
 */
class LegacyProductImporter
{
    public const CONNECTION = 'legacy';

    private const MITRA_UTAMA_OWNER_ID = 1;

    private const MIN_BARCODE_LENGTH = 6;

    private const FACTOR_TOLERANCE = 0.0000001;

    /** @var array<string, array{0: string, 1: string}> old unit name => [code, name] for units with a short code in the new system */
    private const STANDARD_UNITS = [
        'PCS' => ['PCS', 'Pieces'], 'DUS' => ['DUS', 'Dus'], 'PAIL' => ['PAIL', 'Pail'], 'KG' => ['KG', 'Kilogram'],
        'GALON' => ['GLN', 'Galon'], 'LEMBAR' => ['LBR', 'Lembar'], 'METER' => ['MTR', 'Meter'], 'ROLL' => ['ROL', 'Roll'],
        'UNIT' => ['UNIT', 'Unit'], 'KALENG' => ['KLG', 'Kaleng'], 'SET' => ['SET', 'Set'], 'BATANG' => ['BTG', 'Batang'],
        'SAK' => ['SAK', 'Sak'], 'PASANG' => ['PSG', 'Pasang'], 'BUNGKUS' => ['BKS', 'Bungkus'], 'POT' => ['POT', 'Pot'],
        'PAK' => ['PAK', 'Pak'], 'BOTOL' => ['BTL', 'Botol'], 'KUBIK' => ['KUBIK', 'Kubik'], 'GRAM' => ['GR', 'Gram'],
        'GEN' => ['GEN', 'Gen'], 'IKAT' => ['IKAT', 'Ikat'], 'LITER' => ['LTR', 'Liter'], 'LUSIN' => ['LSN', 'Lusin'],
    ];

    private const DOZEN = 12.0;

    private const FALLBACK_CATEGORY = 'Lain-lain';

    public function __construct(private readonly ProductService $products) {}

    /**
     * @return ImportResult
     */
    public function import(): array
    {
        $legacy = DB::connection(self::CONNECTION);

        $unitNames = $legacy->table('satuans')->pluck('nama', 'id');
        $unitCountBefore = Unit::query()->count();

        foreach ($unitNames->map(fn (?string $name): string => trim((string) $name))->filter() as $unitName) {
            $this->unitFor($unitName);
        }

        $newUnitCount = Unit::query()->count() - $unitCountBefore;
        $brandNames = $legacy->table('brands')->pluck('nama', 'id');
        $categoryNames = $legacy->table('jenis')->pluck('nama', 'id');
        $items = $legacy->table('barangs')->where('owners_id', self::MITRA_UTAMA_OWNER_ID)->orderBy('id')->get();
        $priceRows = $this->activePriceRows();

        $skus = $this->skusByLegacyId($items->pluck('id'));
        $existingSkus = Product::withTrashed()->pluck('sku')->map(fn (string $sku): string => Str::lower($sku))->flip();
        $usedBarcodes = ProductUnit::query()->whereNotNull('barcode')->pluck('barcode')->map(fn (string $barcode): string => Str::lower($barcode))->flip();

        $result = ['created' => 0, 'existing' => 0, 'extra_units' => 0, 'new_units' => $newUnitCount, 'review' => []];

        foreach ($items as $item) {
            $sku = $skus[$item->id];

            if ($existingSkus->has(Str::lower($sku))) {
                $result['existing']++;

                continue;
            }

            $name = trim((string) $item->nama);
            $rows = $priceRows[$item->id] ?? collect();
            $buyRow = $rows->firstWhere('ket', 'beli');
            $sellRows = $rows->where('ket', 'jual')->values();
            $baseUnitId = $buyRow->satuans_id ?? $sellRows->first()?->satuans_id;

            if ($baseUnitId === null) {
                $result['review'][] = $this->reviewRow($sku, $name, 'Satuan', '-', 'Barang tidak punya satuan aktif, tidak diimpor');

                continue;
            }

            $baseName = trim((string) $unitNames[$baseUnitId]);
            $baseCost = $buyRow !== null && $buyRow->harga > 1 ? (float) $buyRow->harga : 0.0;
            $barcode = $this->usableBarcode($item->barcode, $usedBarcodes);

            if ($barcode === null && $this->isRealBarcode($item->barcode)) {
                $result['review'][] = $this->reviewRow($sku, $name, 'Barcode', (string) $item->barcode, 'Barcode tidak valid atau sudah dipakai barang lain, dikosongkan');
            }

            $unitRows = [$this->unitRow($baseName, 1.0, $baseCost, (float) ($sellRows->firstWhere('satuans_id', $baseUnitId)->harga ?? 0), $barcode, true)];
            $usedUnitCodes = [$unitRows[0]['unit']['code']];

            foreach ($sellRows->where('satuans_id', '!=', $baseUnitId) as $sellRow) {
                $extraName = trim((string) $unitNames[$sellRow->satuans_id]);
                $factor = $this->extraUnitFactor($extraName, $baseName, (float) $sellRow->konversi);
                $unitRow = $factor === null ? null : $this->unitRow($extraName, $factor, round($baseCost * $factor, 2), (float) $sellRow->harga, null, false);

                if ($unitRow === null || in_array($unitRow['unit']['code'], $usedUnitCodes, true)) {
                    $result['review'][] = $this->reviewRow($sku, $name, "Satuan {$extraName}", (string) $sellRow->harga, "Isi per {$baseName} tidak diketahui, satuan tidak diimpor");

                    continue;
                }

                $unitRows[] = $unitRow;
                $usedUnitCodes[] = $unitRow['unit']['code'];
                $result['extra_units']++;
            }

            $this->products->create([
                'sku' => $sku,
                'name' => $name,
                'category' => ['name' => trim((string) ($categoryNames[$item->jenis_id] ?? '')) ?: self::FALLBACK_CATEGORY],
                'brand' => $this->brandOf($brandNames[$item->brands_id] ?? null),
                'min_stock' => max(0, (int) $item->minimal_stok),
                'is_active' => $item->status_aktif === 'Aktif',
                'units' => $unitRows,
            ]);

            $existingSkus->put(Str::lower($sku), 0);

            if ($barcode !== null) {
                $usedBarcodes->put(Str::lower($barcode), 0);
            }

            $result['created']++;
        }

        return $result;
    }

    /**
     * The newest price row per item, unit and kind (buy or sell) that is not soft deleted.
     *
     * @return Collection<string, Collection<int, object>> rows grouped by old item id
     */
    private function activePriceRows(): Collection
    {
        return DB::connection(self::CONNECTION)->table('konversi')
            ->join('barangs', 'barangs.id', '=', 'konversi.barangs_id')
            ->where('barangs.owners_id', self::MITRA_UTAMA_OWNER_ID)
            ->whereNull('konversi.deleted_at')
            ->orderBy('konversi.created_at')
            ->get(['konversi.barangs_id', 'konversi.satuans_id', 'konversi.konversi', 'konversi.harga', 'konversi.ket'])
            ->keyBy(fn (object $row): string => "{$row->barangs_id}|{$row->satuans_id}|{$row->ket}")
            ->groupBy('barangs_id');
    }

    /**
     * @param  Collection<int, string>  $legacyIds
     * @return array<string, string> SKU per old item id
     */
    private function skusByLegacyId(Collection $legacyIds): array
    {
        $withoutSpaces = fn (string $id): string => preg_replace('/\s+/', '', $id);
        $clashing = $legacyIds->countBy(fn (string $id): string => Str::lower($withoutSpaces($id)))->filter(fn (int $count): bool => $count > 1);

        return $legacyIds->mapWithKeys(fn (string $id): array => [
            $id => $clashing->has(Str::lower($withoutSpaces($id))) && $withoutSpaces($id) !== trim($id)
                ? preg_replace('/\s+/', '-', trim($id))
                : $withoutSpaces($id),
        ])->all();
    }

    /**
     * What one unit of the item holds in base units. An old factor other than 1 was set on purpose; a 1 only
     * counts when the unit name carries the number. Null means it cannot be told.
     */
    private function extraUnitFactor(string $unitName, string $baseName, float $oldFactor): ?float
    {
        $factor = match (true) {
            abs($oldFactor - 1.0) > self::FACTOR_TOLERANCE => $oldFactor,
            (bool) preg_match('/^(\d+)\s+(.+)$/u', $unitName, $parts) && Str::lower($parts[2]) === Str::lower($baseName) => (float) $parts[1],
            Str::upper($unitName) === 'LUSIN' => self::DOZEN,
            default => null,
        };

        $factor = $factor === null ? null : round($factor, 3);

        return $factor === null || $factor <= 0 ? null : $factor;
    }

    /**
     * @return array<string, mixed> a unit row as {@see ProductService} takes it
     */
    private function unitRow(string $oldUnitName, float $factor, float $cost, float $price, ?string $barcode, bool $isBase): array
    {
        return [
            'unit' => ['code' => $this->unitFor($oldUnitName)->code],
            'conversion_factor' => $factor,
            'barcode' => $barcode,
            'is_sellable' => true,
            'cost_price' => $cost,
            'selling_price' => $price,
            'is_default_sale' => $isBase,
        ];
    }

    /**
     * The unit of the new store for an old unit name, created when the store does not have it yet.
     */
    private function unitFor(string $oldName): Unit
    {
        [$code, $name] = self::STANDARD_UNITS[Str::upper($oldName)] ?? [$this->codeFromName($oldName), Str::title(Str::lower($oldName))];

        return Unit::query()->find($code)
            ?? Unit::query()->where('name', $name)->first()
            ?? Unit::query()->create(['code' => $code, 'name' => $name]);
    }

    /**
     * Codes allow letters, digits, spaces, dots, underscores and dashes, up to the length limit.
     */
    private function codeFromName(string $name): string
    {
        $code = preg_replace('/[^A-Za-z0-9 ._-]+/', '-', Unit::normalizeCode($name));

        return Str::limit(ltrim($code, ' ._-'), Unit::CODE_MAX_LENGTH, '');
    }

    /**
     * @return array{name: string}|null
     */
    private function brandOf(?string $name): ?array
    {
        return filled(trim((string) $name)) ? ['name' => trim($name)] : null;
    }

    private function isRealBarcode(mixed $barcode): bool
    {
        return filled($barcode) && ! in_array(trim((string) $barcode), ['0', '1'], true);
    }

    /**
     * @param  Collection<string, int>  $usedBarcodes  lower-case barcodes already taken, as keys
     */
    private function usableBarcode(mixed $barcode, Collection $usedBarcodes): ?string
    {
        $barcode = trim((string) $barcode);

        if (! $this->isRealBarcode($barcode) || ! preg_match('/^[0-9A-Za-z-]+$/', $barcode) || strlen($barcode) < self::MIN_BARCODE_LENGTH) {
            return null;
        }

        return $usedBarcodes->has(Str::lower($barcode)) ? null : $barcode;
    }

    /**
     * @return ReviewRow
     */
    private function reviewRow(string $sku, string $name, string $subject, string $value, string $reason): array
    {
        return ['sku' => $sku, 'name' => $name, 'subject' => $subject, 'value' => $value, 'reason' => $reason];
    }
}

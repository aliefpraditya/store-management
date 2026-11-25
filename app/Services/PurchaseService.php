<?php

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Exceptions\PurchaseException;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Purchases (nota) from suppliers. A purchase says what is ordered, in which unit and at what price; it changes no stock
 * and no cost price, which happens when the goods are received. It starts as a draft that can be changed freely; confirming
 * it locks its lines and prices. Nothing is deleted: a wrong nota is cancelled with a reason.
 *
 * `client_uuid` makes a double submit return the first purchase instead of making a second.
 *
 * @phpstan-type PurchaseItemInput array{product_id: int, unit_code: string, quantity: numeric-string|float|int, unit_cost: numeric-string|float|int, notes?: string|null}
 * @phpstan-type PurchaseInput array{client_uuid?: string, supplier_id: int, supplier_document_number?: string|null, purchase_date: string, expected_date?: string|null, carrier_id?: int|null, warehouse_id: int, notes?: string|null, items: list<PurchaseItemInput>}
 * @phpstan-type PurchaseFilters array{search: string|null, supplier: int|null, carrier: int|null, status: string|null, from: string|null, to: string|null}
 */
class PurchaseService
{
    private const NUMBER_TYPE = 'PB';

    public function __construct(
        private readonly DocumentNumberGenerator $documentNumbers,
        private readonly PurchaseAttachmentService $attachments,
    ) {}

    /**
     * Newest first, narrowed by a search on the number, the supplier's nota number or the supplier's name, by supplier,
     * carrier, status and the day of the purchase.
     *
     * @param  PurchaseFilters  $filters
     * @return LengthAwarePaginator<int, Purchase>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return Purchase::query()
            ->with(['supplier', 'carrier'])
            ->withCount(['items', 'attachments'])
            ->when(filled($filters['search']), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$filters['search']}%")
                ->orWhere('supplier_document_number', 'like', "%{$filters['search']}%")
                ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', "%{$filters['search']}%"))))
            ->when($filters['supplier'], fn (Builder $query, int $supplierId) => $query->where('supplier_id', $supplierId))
            ->when($filters['carrier'], fn (Builder $query, int $carrierId) => $query->where('carrier_id', $carrierId))
            ->when(PurchaseStatus::tryFrom((string) $filters['status']), fn (Builder $query, PurchaseStatus $status) => $query->where('status', $status))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->where('purchase_date', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->where('purchase_date', '<=', $to))
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Makes a draft purchase with its lines, and keeps the nota files that came with it.
     *
     * @param  PurchaseInput  $purchaseInput
     * @param  list<UploadedFile>  $files
     *
     * @throws PurchaseException
     */
    public function create(User $creator, array $purchaseInput, array $files = []): Purchase
    {
        $alreadyCreated = $this->alreadyCreated($purchaseInput['client_uuid'] ?? '', $creator);

        if ($alreadyCreated !== null) {
            return $alreadyCreated;
        }

        try {
            $purchase = DB::transaction(fn (): Purchase => $this->createWithItems($creator, $purchaseInput));
        } catch (UniqueConstraintViolationException $exception) {
            return $this->alreadyCreated($purchaseInput['client_uuid'] ?? '', $creator) ?? throw $exception;
        }

        $this->attachments->storeMany($purchase, $files, $creator);

        return $purchase;
    }

    /**
     * Changes a draft: its header and all of its lines, which are replaced by the ones sent. A confirmed purchase is locked.
     *
     * @param  PurchaseInput  $purchaseInput
     * @param  list<UploadedFile>  $files
     *
     * @throws PurchaseException
     */
    public function update(Purchase $purchase, User $editor, array $purchaseInput, array $files = []): Purchase
    {
        $updatedPurchase = DB::transaction(function () use ($purchase, $purchaseInput): Purchase {
            $lockedPurchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

            if (! $lockedPurchase->status->isEditable()) {
                throw PurchaseException::notEditable();
            }

            $lines = $this->linesOf($purchaseInput['items']);

            $lockedPurchase->forceFill([...$this->headerOf($purchaseInput), 'total_amount' => $this->totalOf($lines)])->save();
            $lockedPurchase->items()->delete();
            $lockedPurchase->items()->createMany($lines);

            return $lockedPurchase;
        });

        $this->attachments->storeMany($updatedPurchase, $files, $editor);

        return $updatedPurchase;
    }

    /**
     * Turns a draft with nothing to pay (every line free, as on a supplier's replacement nota) into an order. Any other nota
     * is paid in advance, so it is confirmed with `confirmAndPay()` instead. From then on its lines and prices are locked.
     *
     * @throws PurchaseException
     */
    public function confirm(Purchase $purchase, User $confirmer): Purchase
    {
        return DB::transaction(function () use ($purchase, $confirmer): Purchase {
            $lockedPurchase = $this->lockedConfirmable($purchase);

            if ($this->hasAmountToPay($lockedPurchase)) {
                throw PurchaseException::paymentRequired();
            }

            return $this->markOrdered($lockedPurchase, $confirmer);
        });
    }

    /**
     * Turns a draft into an order and records that the whole nota was paid, in one step ("Konfirmasi & Bayar").
     *
     * @param  array{payment_method: string, paid_date: string, payment_notes?: string|null}  $paymentInput
     *
     * @throws PurchaseException
     */
    public function confirmAndPay(Purchase $purchase, User $payer, array $paymentInput): Purchase
    {
        return DB::transaction(function () use ($purchase, $payer, $paymentInput): Purchase {
            $lockedPurchase = $this->lockedConfirmable($purchase);

            if (! $this->hasAmountToPay($lockedPurchase)) {
                throw PurchaseException::nothingToPay();
            }

            return $this->markOrdered($lockedPurchase, $payer, [
                'paid_date' => $paymentInput['paid_date'],
                'paid_amount' => $lockedPurchase->total_amount,
                'payment_method' => $paymentInput['payment_method'],
                'payment_notes' => filled($paymentInput['payment_notes'] ?? null) ? trim($paymentInput['payment_notes']) : null,
                'paid_by' => $payer->id,
                'paid_by_name' => $payer->name,
            ]);
        });
    }

    /**
     * The draft purchase, locked for the rest of the transaction, once it is fit to be confirmed.
     *
     * @throws PurchaseException
     */
    private function lockedConfirmable(Purchase $purchase): Purchase
    {
        $lockedPurchase = Purchase::query()->with('supplier')->lockForUpdate()->findOrFail($purchase->id);

        if ($lockedPurchase->status !== PurchaseStatus::Draft) {
            throw PurchaseException::notConfirmable();
        }

        if (! $lockedPurchase->items()->exists()) {
            throw PurchaseException::withoutItems();
        }

        if (! $lockedPurchase->supplier->is_active) {
            throw PurchaseException::supplierInactive();
        }

        return $lockedPurchase;
    }

    private function hasAmountToPay(Purchase $purchase): bool
    {
        return BigDecimal::of((string) $purchase->total_amount)->isPositive();
    }

    /**
     * @param  array<string, mixed>  $payment  the payment columns, when the nota is paid as it is confirmed
     */
    private function markOrdered(Purchase $purchase, User $confirmer, array $payment = []): Purchase
    {
        $purchase->forceFill([
            ...$payment,
            'status' => PurchaseStatus::Ordered,
            'confirmed_by' => $confirmer->id,
            'confirmed_by_name' => $confirmer->name,
            'confirmed_at' => now(),
        ])->save();

        return $purchase;
    }

    /**
     * Cancels a purchase that nothing has been received against; it stays on the list as cancelled, with the reason.
     *
     * @throws PurchaseException
     */
    public function cancel(Purchase $purchase, string $reason): Purchase
    {
        return DB::transaction(function () use ($purchase, $reason): Purchase {
            $lockedPurchase = Purchase::query()->with('shipment')->lockForUpdate()->findOrFail($purchase->id);

            if (! $lockedPurchase->status->isCancellable()) {
                throw PurchaseException::notCancellable();
            }

            if ($lockedPurchase->shipment !== null) {
                throw PurchaseException::inShipment($lockedPurchase->shipment->number);
            }

            $lockedPurchase->forceFill([
                'status' => PurchaseStatus::Cancelled,
                'cancel_reason' => trim($reason),
                'cancelled_at' => now(),
            ])->save();

            return $lockedPurchase;
        });
    }

    /**
     * @param  PurchaseInput  $purchaseInput
     *
     * @throws PurchaseException
     */
    private function createWithItems(User $creator, array $purchaseInput): Purchase
    {
        $lines = $this->linesOf($purchaseInput['items']);

        $purchase = Purchase::query()->create([
            ...$this->headerOf($purchaseInput),
            'client_uuid' => $purchaseInput['client_uuid'],
            'number' => $this->documentNumbers->next(self::NUMBER_TYPE, CarbonImmutable::parse($purchaseInput['purchase_date'])),
            'status' => PurchaseStatus::Draft,
            'total_amount' => $this->totalOf($lines),
            'created_by' => $creator->id,
            'created_by_name' => $creator->name,
        ]);
        $purchase->items()->createMany($lines);

        return $purchase;
    }

    /**
     * The fields of a purchase that the form edits besides its lines.
     *
     * @param  PurchaseInput  $purchaseInput
     * @return array<string, mixed>
     *
     * @throws PurchaseException
     */
    private function headerOf(array $purchaseInput): array
    {
        $supplier = Supplier::query()->findOrFail($purchaseInput['supplier_id']);

        if (! $supplier->is_active) {
            throw PurchaseException::supplierInactive();
        }

        return [
            'supplier_id' => $supplier->id,
            'supplier_document_number' => filled($purchaseInput['supplier_document_number'] ?? null) ? trim($purchaseInput['supplier_document_number']) : null,
            'purchase_date' => $purchaseInput['purchase_date'],
            'expected_date' => filled($purchaseInput['expected_date'] ?? null) ? $purchaseInput['expected_date'] : null,
            'carrier_id' => $purchaseInput['carrier_id'] ?? null,
            'warehouse_id' => $purchaseInput['warehouse_id'],
            'notes' => filled($purchaseInput['notes'] ?? null) ? trim($purchaseInput['notes']) : null,
        ];
    }

    /**
     * The lines as they are kept: the unit and conversion the product has now, the quantity and price in that unit, and the
     * same in the base unit. A product can be on a nota once per unit.
     *
     * @param  list<PurchaseItemInput>  $items
     * @return list<array<string, mixed>>
     *
     * @throws PurchaseException
     */
    private function linesOf(array $items): array
    {
        $products = Product::query()->with('productUnits')->whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');
        $seenLines = [];
        $lines = [];

        foreach ($items as $item) {
            $product = $products[$item['product_id']] ?? throw PurchaseException::productUnavailable();

            if (! $product->is_active) {
                throw PurchaseException::productInactive($product->name);
            }

            if ($product->is_damaged_goods) {
                throw PurchaseException::damagedGoodsNotBought($product->name);
            }

            $unit = $product->productUnits->firstWhere('unit_code', $item['unit_code']) ?? throw PurchaseException::unitNotOfProduct($product->name, $item['unit_code']);

            $lineKey = "{$product->id}|{$unit->unit_code}";

            if (isset($seenLines[$lineKey])) {
                throw PurchaseException::duplicateItem($product->name, $unit->unit_code);
            }

            $seenLines[$lineKey] = true;

            $conversionFactor = BigDecimal::of((string) $unit->conversion_factor);
            $quantity = BigDecimal::of((string) $item['quantity'])->toScale(3, RoundingMode::HalfUp);
            $unitCost = BigDecimal::of((string) $item['unit_cost'])->toScale(2, RoundingMode::HalfUp);

            $lines[] = [
                'product_id' => $product->id,
                'unit_code' => $unit->unit_code,
                'conversion_factor' => (string) $conversionFactor->toScale(3),
                'quantity' => (string) $quantity,
                'base_quantity' => (string) $quantity->multipliedBy($conversionFactor)->toScale(3, RoundingMode::HalfUp),
                'unit_cost' => (string) $unitCost,
                'base_unit_cost' => (string) $unitCost->dividedBy($conversionFactor, 4, RoundingMode::HalfUp),
                'subtotal' => (string) $quantity->multipliedBy($unitCost)->toScale(2, RoundingMode::HalfUp),
                'notes' => filled($item['notes'] ?? null) ? trim($item['notes']) : null,
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function totalOf(array $lines): string
    {
        $total = array_reduce($lines, fn (BigDecimal $sum, array $line): BigDecimal => $sum->plus($line['subtotal']), BigDecimal::zero());

        return (string) $total->toScale(2);
    }

    /**
     * The purchase a retried request made the first time, so a double tap cannot make it twice. A marker that belongs to
     * someone else's purchase is refused: it is not a retry.
     *
     * @throws PurchaseException
     */
    private function alreadyCreated(string $clientUuid, User $creator): ?Purchase
    {
        if ($clientUuid === '') {
            return null;
        }

        $createdPurchase = Purchase::query()->where('client_uuid', $clientUuid)->first();

        if ($createdPurchase === null) {
            return null;
        }

        return $createdPurchase->created_by === $creator->id ? $createdPurchase : throw PurchaseException::purchaseIdInUse();
    }
}

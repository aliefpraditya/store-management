<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\SaleException;
use App\Models\CashShift;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rings up a sale in one transaction: the receipt with its lines and payments, and the stock taken from the
 * register's warehouse. Prices and costs are read from the database, never from the cashier's screen. The
 * `client_uuid` makes a double submit return the first receipt instead of selling twice.
 *
 * @phpstan-type SaleLine array{product_unit_id: int, quantity: numeric-string|float|int}
 * @phpstan-type SalePaymentInput array{method: string, amount: numeric-string|float|int, reference?: string|null}
 * @phpstan-type SaleData array{client_uuid: string, customer_name?: string|null, items: list<SaleLine>, payments: list<SalePaymentInput>}
 */
class SaleService
{
    private const RECEIPT_TYPE = 'INV';

    public function __construct(
        private readonly StockService $stockLedger,
        private readonly DocumentNumberGenerator $documentNumbers,
    ) {}

    /**
     * @param  SaleData  $saleInput
     *
     * @throws SaleException
     */
    public function create(CashShift $shift, User $cashier, array $saleInput): Sale
    {
        return DB::transaction(function () use ($shift, $cashier, $saleInput): Sale {
            $shift = CashShift::query()->with('register')->lockForUpdate()->findOrFail($shift->id);

            // Checked under the shift lock so two submits of the same cart cannot both get past it.
            $alreadyRecordedSale = Sale::query()->where('client_uuid', $saleInput['client_uuid'])->first();

            if ($alreadyRecordedSale !== null) {
                return $this->receiptOfResubmit($alreadyRecordedSale, $cashier);
            }

            if ($shift->status !== CashShiftStatus::Open) {
                throw SaleException::shiftNotOpen();
            }

            $lines = $this->priceLines($saleInput['items']);

            $this->requireStock($lines, $shift->register->warehouse_id);

            $total = $lines->reduce(fn (BigDecimal $sum, array $line): BigDecimal => $sum->plus($line['subtotal']), BigDecimal::zero());
            [$paidTotal, $changeAmount] = $this->settle($total, $saleInput['payments']);

            $sale = $this->recordReceipt($shift, $cashier, $saleInput, $total, $paidTotal, $changeAmount);
            $this->recordLines($sale, $lines, $cashier);
            $this->recordPayments($sale, $saleInput['payments']);

            return $sale->load(['items', 'payments']);
        }, StockService::DEADLOCK_ATTEMPTS);
    }

    /**
     * A double submit gets the receipt it already made. A `client_uuid` that belongs to someone else's sale is
     * refused, so a receipt cannot be read by sending its uuid.
     *
     * @throws SaleException
     */
    private function receiptOfResubmit(Sale $alreadyRecordedSale, User $cashier): Sale
    {
        if ($alreadyRecordedSale->cashier_id !== $cashier->id) {
            throw SaleException::receiptIdInUse();
        }

        return $alreadyRecordedSale->load(['items', 'payments']);
    }

    /**
     * @param  SaleData  $saleInput
     */
    private function recordReceipt(CashShift $shift, User $cashier, array $saleInput, BigDecimal $total, BigDecimal $paidTotal, BigDecimal $changeAmount): Sale
    {
        $register = $shift->register;

        return Sale::query()->create([
            'client_uuid' => $saleInput['client_uuid'],
            'number' => $this->documentNumbers->next(self::RECEIPT_TYPE, now(), $register),
            'register_id' => $register->id,
            'cash_shift_id' => $shift->id,
            'warehouse_id' => $register->warehouse_id,
            'sold_at' => now(),
            'business_date' => $shift->business_date,
            'cashier_id' => $cashier->id,
            'cashier_name' => $cashier->name,
            'customer_name' => filled($saleInput['customer_name'] ?? null) ? trim($saleInput['customer_name']) : null,
            'subtotal' => (string) $total,
            'total' => (string) $total,
            'paid_total' => (string) $paidTotal,
            'change_amount' => (string) $changeAmount,
            'status' => SaleStatus::Completed,
        ]);
    }

    /**
     * @param  Collection<int, array{product_id: int, base_quantity: string, attributes: array<string, mixed>}>  $lines
     */
    private function recordLines(Sale $sale, Collection $lines, User $cashier): void
    {
        foreach ($lines as $line) {
            $sale->items()->create($line['attributes']);

            $this->stockLedger->record(
                $line['product_id'],
                $sale->warehouse_id,
                StockMovementType::Sale,
                '-'.$line['base_quantity'],
                $sale,
                $cashier->id,
            );
        }
    }

    /**
     * @param  list<SalePaymentInput>  $payments
     */
    private function recordPayments(Sale $sale, array $payments): void
    {
        foreach ($payments as $payment) {
            $sale->payments()->create([
                'method' => PaymentMethod::from($payment['method']),
                'amount' => $payment['amount'],
                'reference' => filled($payment['reference'] ?? null) ? trim($payment['reference']) : null,
            ]);
        }
    }

    /**
     * One entry per cart line with the price, cost and base quantity worked out from the product unit.
     *
     * @param  list<SaleLine>  $items
     * @return Collection<int, array{product_id: int, product_name: string, base_unit_code: string, base_quantity: string, subtotal: BigDecimal, attributes: array<string, mixed>}>
     *
     * @throws SaleException
     */
    private function priceLines(array $items): Collection
    {
        $productUnitsById = ProductUnit::query()
            ->with(['product', 'unit'])
            ->whereIn('id', array_column($items, 'product_unit_id'))
            ->get()
            ->keyBy('id');

        return collect($items)->map(function (array $cartLine) use ($productUnitsById): array {
            $productUnit = $productUnitsById->get($cartLine['product_unit_id']);
            $product = $productUnit?->product;

            if ($productUnit === null || $product === null) {
                throw SaleException::lineNotFound();
            }

            if ($product->trashed() || ! $product->is_active) {
                throw SaleException::productUnavailable($product->name);
            }

            if (! $productUnit->is_sellable) {
                throw SaleException::notSellable($product->name);
            }

            $quantity = BigDecimal::of((string) $cartLine['quantity']);
            $baseQuantity = $quantity->multipliedBy($productUnit->conversion_factor)->toScale(3, RoundingMode::HalfUp);
            $subtotal = $quantity->multipliedBy($productUnit->selling_price)->toScale(2, RoundingMode::HalfUp);

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'base_unit_code' => $product->base_unit_code,
                'base_quantity' => (string) $baseQuantity,
                'subtotal' => $subtotal,
                'attributes' => [
                    'product_id' => $product->id,
                    'product_unit_id' => $productUnit->id,
                    'product_name' => $product->name,
                    'unit_name' => $productUnit->unit_code,
                    'quantity' => (string) $quantity,
                    'conversion_snapshot' => $productUnit->conversion_factor,
                    'base_quantity' => (string) $baseQuantity,
                    'unit_price' => $productUnit->selling_price,
                    'cost_total' => (string) $quantity->multipliedBy($productUnit->cost_price)->toScale(2, RoundingMode::HalfUp),
                    'is_cost_estimated' => false,
                    'is_manual_price' => false,
                    'subtotal' => (string) $subtotal,
                ],
            ];
        });
    }

    /**
     * Every product must have enough in the warehouse for all its lines together, even when sold in different units.
     * Stock rows are locked in a fixed order so two sales cannot wait on each other.
     *
     * @param  Collection<int, array{product_id: int, product_name: string, base_unit_code: string, base_quantity: string}>  $lines
     *
     * @throws SaleException
     */
    private function requireStock(Collection $lines, int $warehouseId): void
    {
        $linesByProduct = $lines->groupBy('product_id')->sortKeys();

        foreach ($linesByProduct as $productId => $productLines) {
            $required = $productLines->reduce(fn (BigDecimal $sum, array $line): BigDecimal => $sum->plus($line['base_quantity']), BigDecimal::zero());
            $available = BigDecimal::of($this->stockLedger->lockedQuantity($productId, $warehouseId));

            if ($required->isGreaterThan($available)) {
                throw SaleException::insufficientStock($productLines[0]['product_name'], $this->quantityText($available, $productLines[0]['base_unit_code']));
            }
        }
    }

    /**
     * Checks the payments cover the total and that any change can come out of the cash received.
     *
     * @param  list<SalePaymentInput>  $payments
     * @return array{0: BigDecimal, 1: BigDecimal} paid total and change
     *
     * @throws SaleException
     */
    private function settle(BigDecimal $total, array $payments): array
    {
        $paidTotal = BigDecimal::zero();
        $cashReceived = BigDecimal::zero();

        foreach ($payments as $payment) {
            $amount = BigDecimal::of((string) $payment['amount']);
            $paidTotal = $paidTotal->plus($amount);

            if ($payment['method'] === PaymentMethod::Cash->value) {
                $cashReceived = $cashReceived->plus($amount);
            }
        }

        if ($paidTotal->isLessThan($total)) {
            throw SaleException::paymentTooLow();
        }

        $change = $paidTotal->minus($total);

        if ($change->isGreaterThan($cashReceived)) {
            throw SaleException::changeExceedsCash();
        }

        return [$paidTotal->toScale(2), $change->toScale(2)];
    }

    private function quantityText(BigDecimal $quantity, string $unitCode): string
    {
        $text = rtrim(rtrim((string) $quantity->toScale(3), '0'), '.');

        return "{$text} {$unitCode}";
    }
}

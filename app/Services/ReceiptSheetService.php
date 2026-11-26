<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Http\Resources\PrintableSaleResource;
use App\Models\CustomerOrder;
use App\Models\Sale;

/**
 * What the printable receipt page shows, for every screen that prints a receipt: the cashier's receipt dialog and the
 * store's receipt history.
 */
class ReceiptSheetService
{
    public function __construct(private readonly StoreSettingService $settings) {}

    /**
     * @param  bool  $isCopy  whether the sheet says SALINAN, from the second print on
     * @return array<string, mixed>
     */
    public function forSale(Sale $sale, bool $isCopy): array
    {
        return PrintableSaleResource::make(
            $sale->load(['items', 'payments']),
            $isCopy,
            $this->settings->letterhead(),
            $this->settings->receiptFooter(),
            $this->settings->paperWidth(),
            $this->orderMadeWith($sale),
        )->resolve();
    }

    /**
     * The customer order made in the same checkout as the receipt, unless it was cancelled. It is printed on the receipt
     * itself, so the customer holds one paper for what was paid and what was ordered.
     */
    private function orderMadeWith(Sale $sale): ?CustomerOrder
    {
        return CustomerOrder::query()
            ->with(['items', 'payments'])
            ->where('origin_sale_id', $sale->id)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->first();
    }
}

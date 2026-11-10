<?php

namespace App\Http\Resources;

use App\Models\CashShift;
use App\Services\ShiftService;
use App\Services\StoreSettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The end-of-shift report as the cashier gets it on paper: what the cashier counted and hands over, and how many receipts
 * there were, but no amount the system holds (sales, payments, expected cash, difference). The cashier closes the
 * drawer blind, so the paper must not show what the screen hid; the Admin prints the full report from the shift recap.
 * Expects `register` to be loaded.
 *
 * @phpstan-import-type Letterhead from StoreSettingService
 *
 * @mixin CashShift
 */
class BlindShiftReportResource extends JsonResource
{
    /**
     * @param  Letterhead  $letterhead
     * @param  array{sale_count: int, cod: array{delivered_count: int, undelivered_count: int}, opening_cash: string}  $cashierSummary  {@see ShiftService::summarizeForCashier()}
     */
    public function __construct(CashShift $resource, private readonly array $cashierSummary, private readonly array $letterhead, private readonly int $paperWidth)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'is_blind' => true,
            'label' => $this->label(),
            'register_name' => $this->register->name,
            'cashier_name' => $this->cashier_name,
            'business_date' => $this->business_date->toDateString(),
            'opened_at' => $this->opened_at->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'opening_cash' => $this->cashierSummary['opening_cash'],
            'sale_count' => $this->cashierSummary['sale_count'],
            'cod' => $this->cashierSummary['cod'],
            'counted_cash' => $this->counted_cash,
            'deposit_amount' => $this->deposit_amount,
            'drawer_left_cash' => $this->drawer_left_cash,
            'closing_note' => $this->closing_note,
            'store' => $this->letterhead,
            'paper_width' => $this->paperWidth,
        ];
    }
}

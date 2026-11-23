<?php

namespace App\Http\Resources;

use App\Enums\CodSettlementStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Http\Middleware\EnsureUserCanAccessStore;
use App\Models\DeliveryNote;
use App\Models\Sale;
use App\Services\CodSettlementService;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The money of the COD receipt a delivery note belongs to, for the note's page: what is owed after returns, whether the goods
 * were delivered so the money can be received, and what was received. The resource is a row of
 * {@see CodSettlementService::rowOf()} with the note it is shown on.
 *
 * @property array{row: array{sale: Sale, offset_amount: BigDecimal, amount_due: BigDecimal, has_unreturned_goods: bool, is_goods_delivered: bool}, note: DeliveryNote} $resource
 */
class DeliveryNoteCodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = $this->resource['row'];
        $sale = $row['sale'];
        $settlement = $sale->codSettlement;
        $noteAddress = ['tenant' => tenant('code'), 'delivery_note' => $this->resource['note']->id];

        return [
            'sale_number' => $sale->number,
            'state' => match (true) {
                $settlement === null => 'unsettled',
                $settlement->status === CodSettlementStatus::AwaitingApproval => 'awaiting_approval',
                default => 'settled',
            },
            'is_goods_delivered' => $row['is_goods_delivered'],
            'cod_amount' => $sale->codAmount(),
            'offset_amount' => (string) $row['offset_amount']->toScale(2),
            'amount_due' => (string) $row['amount_due']->toScale(2),
            'has_unreturned_goods' => $row['has_unreturned_goods'],
            'settlement' => $settlement === null ? null : CodSettlementResource::make($settlement)->resolve(),
            'methods' => PaymentMethod::cashierOptions(),
            'can_receive' => EnsureUserCanAccessStore::grants($request, Permission::CodReceive),
            'can_approve' => EnsureUserCanAccessStore::grants($request, Permission::CodApprove),
            'receive_url' => route('tenant.delivery-notes.cod-settlement', $noteAddress),
            'approve_url' => route('tenant.delivery-notes.cod-settlement.approve', $noteAddress),
        ];
    }
}

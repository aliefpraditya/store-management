<?php

namespace App\Models;

use App\Enums\ReturnDisposition;
use Database\Factories\SaleReturnItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lives in the store database. How much of one receipt line comes back, at the price of the receipt, and into
 * which warehouse it goes. `cost_total` is the share of the line's cost that comes back with it.
 */
#[Fillable(['sale_return_id', 'sale_item_id', 'product_id', 'product_unit_id', 'quantity', 'base_quantity', 'unit_price', 'cost_total', 'refund_subtotal', 'disposition', 'destination_warehouse_id', 'condition_note'])]
class SaleReturnItem extends Model
{
    /** @use HasFactory<SaleReturnItemFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'cost_total' => 'decimal:2',
            'refund_subtotal' => 'decimal:2',
            'disposition' => ReturnDisposition::class,
        ];
    }

    /**
     * @return BelongsTo<SaleReturn, $this>
     */
    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    /**
     * @return BelongsTo<SaleItem, $this>
     */
    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}

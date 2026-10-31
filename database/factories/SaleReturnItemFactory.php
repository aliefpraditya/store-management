<?php

namespace Database\Factories;

use App\Enums\ReturnDisposition;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturnItem>
 */
class SaleReturnItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $saleItem = SaleItem::factory()->create();

        return [
            'sale_return_id' => SaleReturn::factory(),
            'sale_item_id' => $saleItem->id,
            'product_id' => $saleItem->product_id,
            'product_unit_id' => $saleItem->product_unit_id,
            'quantity' => 1,
            'base_quantity' => 1,
            'unit_price' => $saleItem->unit_price,
            'cost_total' => 0,
            'refund_subtotal' => $saleItem->unit_price,
            'disposition' => ReturnDisposition::Resellable,
            'destination_warehouse_id' => Warehouse::factory(),
        ];
    }
}

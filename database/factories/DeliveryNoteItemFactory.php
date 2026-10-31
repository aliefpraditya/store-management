<?php

namespace Database\Factories;

use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryNoteItem>
 */
class DeliveryNoteItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $saleItem = SaleItem::factory()->create();

        return [
            'delivery_note_id' => DeliveryNote::factory(),
            'sale_item_id' => $saleItem->id,
            'product_id' => $saleItem->product_id,
            'product_unit_id' => $saleItem->product_unit_id,
            'product_name' => $saleItem->product_name,
            'unit_name' => $saleItem->unit_name,
            'quantity' => 1,
        ];
    }
}

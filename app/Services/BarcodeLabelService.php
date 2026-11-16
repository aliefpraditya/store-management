<?php

namespace App\Services;

use App\Models\Product;

/**
 * Prepares barcode stickers for the items picked on the item list. An item has no barcode of its own: the sticker carries
 * its SKU as a Code 39 barcode, which is what the till finds the item by when it is scanned. Code 39 holds digits, letters
 * and a few signs, so an item whose SKU has any other character cannot be printed.
 *
 * @phpstan-type PickableProduct array{id: int, sku: string, name: string, is_printable: bool}
 * @phpstan-type Label array{name: string, sku: string, copies: int}
 */
class BarcodeLabelService
{
    public const MAX_PRODUCTS = 200;

    public const MAX_COPIES_PER_PRODUCT = 500;

    /** Every sticker is a drawn barcode on the sheet, so this keeps the print window light. */
    public const MAX_LABELS = 500;

    private const CODE_39_PATTERN = '/^[0-9A-Za-z\-. $\/+%]+$/';

    public static function isPrintable(string $sku): bool
    {
        return preg_match(self::CODE_39_PATTERN, $sku) === 1;
    }

    /**
     * The picked items, for the window where the admin sets how many stickers each gets.
     *
     * @param  list<int>  $productIds
     * @return list<PickableProduct>
     */
    public function pickableProducts(array $productIds): array
    {
        return Product::query()
            ->whereIn('id', $productIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'sku', 'name'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'is_printable' => self::isPrintable($product->sku),
            ])
            ->all();
    }

    /**
     * What to print: one entry per item asked for, with how many stickers of it. Items that cannot be printed are left out.
     *
     * @param  array<int, int>  $copiesByProductId  stickers wanted, by product id
     * @return list<Label>
     */
    public function labels(array $copiesByProductId): array
    {
        return Product::query()
            ->whereIn('id', array_keys($copiesByProductId))
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'sku', 'name'])
            ->filter(fn (Product $product): bool => self::isPrintable($product->sku))
            ->map(fn (Product $product): array => [
                'name' => $product->name,
                'sku' => $product->sku,
                'copies' => $copiesByProductId[$product->id],
            ])
            ->values()
            ->all();
    }
}

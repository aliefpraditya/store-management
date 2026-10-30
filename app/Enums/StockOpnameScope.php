<?php

namespace App\Enums;

enum StockOpnameScope: string
{
    /** Every product of a category and/or a brand; at least one of the two is chosen. */
    case Category = 'category';
    case Selected = 'selected';

    public function label(): string
    {
        return match ($this) {
            self::Category => 'Kategori & merek tertentu',
            self::Selected => 'Barang terpilih',
        };
    }
}

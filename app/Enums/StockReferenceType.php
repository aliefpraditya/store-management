<?php

namespace App\Enums;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockOpname;
use Illuminate\Database\Eloquent\Model;

/**
 * What a stock movement points at (`stock_movements.reference_type`). A short name, not a class name, so the
 * ledger survives class renames.
 */
enum StockReferenceType: string
{
    case StockOpname = 'stock_opname';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::StockOpname => StockOpname::class,
            self::Sale => Sale::class,
            self::SaleReturn => SaleReturn::class,
        };
    }
}

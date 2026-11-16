<?php

namespace App\Enums;

enum WarehouseType: string
{
    case Sellable = 'sellable';
    case DeadStock = 'dead_stock';

    /** Where returned goods wait to be inspected; never sold from, and paired with the sellable warehouse they came from. */
    case Returns = 'returns';
}

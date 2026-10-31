<?php

namespace App\Enums;

enum WarehouseType: string
{
    case Sellable = 'sellable';
    case DeadStock = 'dead_stock';
}

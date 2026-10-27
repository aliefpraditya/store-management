<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. A receipt: never deleted, corrected by voiding. Only changes are audited.
 */
class Sale extends Model implements AuditableContract
{
    /** @use HasFactory<SaleFactory> */
    use Auditable, HasFactory;

    /** @var list<string> */
    protected array $auditEvents = ['updated'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'business_date' => 'date',
            'voided_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'legacy_unpaid_amount' => 'decimal:2',
            'is_price_estimated' => 'boolean',
            'status' => SaleStatus::class,
        ];
    }
}

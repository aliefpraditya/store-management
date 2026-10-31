<?php

namespace App\Models;

use App\Enums\SaleStatus;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. A receipt: never deleted, corrected by voiding. Only changes are audited.
 */
#[Fillable(['client_uuid', 'number', 'register_id', 'cash_shift_id', 'warehouse_id', 'sold_at', 'business_date', 'cashier_id', 'cashier_name', 'customer_name', 'subtotal', 'discount_total', 'total', 'paid_total', 'change_amount', 'status'])]
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

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<SalePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /**
     * @return BelongsTo<Register, $this>
     */
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    /**
     * @return BelongsTo<CashShift, $this>
     */
    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }
}

<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SaleReturnProgress;
use App\Enums\SaleStatus;
use Brick\Math\BigDecimal;
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
            'void_cash_refund' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'legacy_unpaid_amount' => 'decimal:2',
            'is_price_estimated' => 'boolean',
            'status' => SaleStatus::class,
            'return_status' => SaleReturnProgress::class,
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
     * @return HasMany<DeliveryNote, $this>
     */
    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    /**
     * What is left to pay when the goods are delivered; zero for a sale that is not COD. Needs `payments` loaded.
     */
    public function codAmount(): string
    {
        return (string) $this->amountPaidBy([PaymentMethod::Cod])->toScale(2);
    }

    /**
     * The cash taken in advance, after the change that was handed back. Needs `payments` loaded.
     */
    public function cashDeposit(): BigDecimal
    {
        return $this->amountPaidBy([PaymentMethod::Cash])->minus((string) $this->change_amount);
    }

    /**
     * What was paid in advance by transfer, QRIS or EDC, which goes back to an account rather than out of a drawer.
     * Needs `payments` loaded.
     */
    public function nonCashDeposit(): BigDecimal
    {
        return $this->amountPaidBy([PaymentMethod::Transfer, PaymentMethod::Qris, PaymentMethod::Edc]);
    }

    /**
     * @param  list<PaymentMethod>  $methods
     */
    private function amountPaidBy(array $methods): BigDecimal
    {
        return $this->payments
            ->whereIn('method', $methods)
            ->reduce(fn (BigDecimal $sum, SalePayment $payment): BigDecimal => $sum->plus((string) $payment->amount), BigDecimal::zero());
    }

    public function isCod(): bool
    {
        return $this->payments->contains('method', PaymentMethod::Cod);
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

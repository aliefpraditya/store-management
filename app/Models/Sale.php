<?php

namespace App\Models;

use App\Enums\DeliveryNoteStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleFulfillment;
use App\Enums\SaleReturnProgress;
use App\Enums\SaleStatus;
use Brick\Math\BigDecimal;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. A receipt: never deleted, corrected by voiding. Only changes are audited.
 */
#[Fillable(['client_uuid', 'number', 'register_id', 'cash_shift_id', 'warehouse_id', 'sold_at', 'business_date', 'cashier_id', 'cashier_name', 'customer_name', 'subtotal', 'discount_total', 'total', 'paid_total', 'change_amount', 'status', 'fulfillment'])]
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
            'void_business_date' => 'date',
            'void_cash_refund' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'legacy_unpaid_amount' => 'decimal:2',
            'is_price_estimated' => 'boolean',
            'status' => SaleStatus::class,
            'fulfillment' => SaleFulfillment::class,
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
     * The delivery notes that carry goods of this receipt: its own, and a combined note of another receipt that also takes
     * these goods. Whether the goods were delivered, and whether the receipt can still be voided, follow these notes.
     *
     * @return BelongsToMany<DeliveryNote, $this>
     */
    public function deliveryNotes(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryNote::class, 'delivery_note_sales');
    }

    /**
     * Completed receipts to be delivered whose goods did not reach the customer yet: no delivery note of theirs has
     * delivered goods, which is also the case while there is no note at all.
     *
     * @param  Builder<Sale>  $query
     */
    public function scopePendingDelivery(Builder $query): void
    {
        $query->where('status', SaleStatus::Completed)
            ->where('fulfillment', SaleFulfillment::Delivery)
            ->whereDoesntHave('deliveryNotes', fn (Builder $notes) => $notes->whereIn('delivery_notes.status', DeliveryNoteStatus::withDeliveredGoods()));
    }

    /**
     * The money received for the COD part once the goods were delivered; none until then.
     *
     * @return HasOne<CodSettlement, $this>
     */
    public function codSettlement(): HasOne
    {
        return $this->hasOne(CodSettlement::class);
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
     * The latest delivery note of a completed receipt that is still on its way (draft or shipped): the one to finish, with
     * what came back and, for COD, the money. Null when there is none. Needs `deliveryNotes` loaded.
     */
    public function openDeliveryNote(): ?DeliveryNote
    {
        if ($this->status !== SaleStatus::Completed) {
            return null;
        }

        return $this->deliveryNotes
            ->filter(fn (DeliveryNote $note): bool => $note->status->isUnfinished())
            ->sortByDesc('id')
            ->first();
    }

    /**
     * The same test as {@see self::scopePendingDelivery()}, for a receipt already in hand. Needs `deliveryNotes` loaded.
     */
    public function isDeliveryPending(): bool
    {
        return $this->status === SaleStatus::Completed
            && $this->fulfillment === SaleFulfillment::Delivery
            && ! $this->deliveryNotes->contains(fn (DeliveryNote $note): bool => $note->status->hasDeliveredGoods());
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

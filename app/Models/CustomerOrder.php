<?php

namespace App\Models;

use App\Enums\OrderPaymentKind;
use App\Enums\OrderStatus;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. Goods the customer ordered that the store does not have yet (a PO). It moves no stock
 * and is no revenue: the sale is made when the customer collects the goods (`sale_id`). `origin_sale_id` is the
 * receipt made together with the order when part of the cart was in stock. `created_by` is a central user id.
 */
#[Fillable(['client_uuid', 'number', 'register_id', 'cash_shift_id', 'warehouse_id', 'status', 'customer_name', 'customer_phone', 'customer_address', 'source_note', 'expected_date', 'total', 'ordered_at', 'business_date', 'ready_at', 'origin_sale_id', 'sale_id', 'cancel_reason', 'cancelled_at', 'created_by', 'created_by_name'])]
class CustomerOrder extends Model implements AuditableContract
{
    use Auditable;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'expected_date' => 'date',
            'total' => 'decimal:2',
            'ordered_at' => 'datetime',
            'business_date' => 'date',
            'ready_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<CustomerOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CustomerOrderItem::class);
    }

    /**
     * @return HasMany<CustomerOrderPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerOrderPayment::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function originSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'origin_sale_id');
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Register, $this>
     */
    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    /**
     * What the customer put down and still has with the store: deposits minus refunds. Needs `payments` loaded.
     */
    public function depositBalance(): BigDecimal
    {
        return $this->payments->reduce(
            fn (BigDecimal $sum, CustomerOrderPayment $payment): BigDecimal => $payment->kind === OrderPaymentKind::Deposit ? $sum->plus((string) $payment->amount) : $sum->minus((string) $payment->amount),
            BigDecimal::zero(),
        );
    }
}

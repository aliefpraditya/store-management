<?php

namespace App\Models;

use App\Enums\OrderPaymentKind;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. Money that went into (`deposit`) or back out of (`refund`) the drawer or an account
 * for a customer order, in the shift it happened in. For a non-cash refund `reference` holds the account it went to.
 */
#[Fillable(['customer_order_id', 'cash_shift_id', 'kind', 'method', 'amount', 'reference', 'paid_at'])]
class CustomerOrderPayment extends Model implements AuditableContract
{
    use Auditable;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'kind' => OrderPaymentKind::class,
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CustomerOrder, $this>
     */
    public function customerOrder(): BelongsTo
    {
        return $this->belongsTo(CustomerOrder::class);
    }
}

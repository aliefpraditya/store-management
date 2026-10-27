<?php

namespace App\Models;

use App\Enums\SaleReturnStatus;
use Database\Factories\SaleReturnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. A return stays `pending` until an admin approves it.
 */
class SaleReturn extends Model implements AuditableContract
{
    /** @use HasFactory<SaleReturnFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'returned_at' => 'datetime',
            'credit_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'status' => SaleReturnStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}

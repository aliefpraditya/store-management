<?php

namespace App\Models;

use App\Enums\OpnameReasonBehavior;
use Database\Factories\OpnameReasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. Why a counted quantity differs from the system's. A reason is never deleted: one that
 * is no longer wanted is switched off, so the opnames that used it keep its name. The ones every store starts with are
 * `is_system`: they keep their behavior and stay on.
 */
#[Fillable(['name', 'behavior', 'is_system', 'is_active'])]
class OpnameReason extends Model implements AuditableContract
{
    /** @use HasFactory<OpnameReasonFactory> */
    use Auditable, HasFactory;

    /**
     * The reasons every store starts with, as name => behavior; an admin can add more (they behave as plain).
     *
     * @var array<string, OpnameReasonBehavior>
     */
    public const DEFAULTS = [
        'Pembelian' => OpnameReasonBehavior::Plain,
        'Cek stok' => OpnameReasonBehavior::Plain,
        'Hilang' => OpnameReasonBehavior::Plain,
        'Rusak' => OpnameReasonBehavior::Damaged,
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'behavior' => OpnameReasonBehavior::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}

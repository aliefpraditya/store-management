<?php

namespace App\Models;

use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Lives in the store database. A category is never deleted: one that is no longer wanted is switched off, so the
 * expenses already filed under it keep their name.
 */
#[Fillable(['name', 'is_active'])]
class ExpenseCategory extends Model implements AuditableContract
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use Auditable, HasFactory;

    /** The categories every store starts with; an admin can add more. */
    public const DEFAULT_NAMES = ['Operasional', 'Listrik/Air', 'Transport/Bensin', 'Upah/Gaji', 'ATK/Perlengkapan', 'Lain-lain'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}

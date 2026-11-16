<?php

namespace App\Models;

use App\Enums\ProvisioningResult;
use App\Enums\ProvisioningStep;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Lives in the central database. It is written while a store's tenancy may still be initialized
 * (a failing migration leaves it so), which is why it must pin the central connection.
 */
#[Fillable(['store_id', 'step', 'result', 'message'])]
class StoreProvisioningLog extends Model
{
    use CentralConnection;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step' => ProvisioningStep::class,
            'result' => ProvisioningResult::class,
        ];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}

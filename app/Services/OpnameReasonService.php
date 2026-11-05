<?php

namespace App\Services;

use App\Enums\OpnameReasonBehavior;
use App\Models\OpnameReason;
use Illuminate\Support\Collection;

/**
 * The reasons an opname variance can be given. The four every store starts with come first; an admin adds more, renames
 * them, and switches them off. A reason is never deleted, so old opnames keep what they were told. A reason an admin
 * adds is plain: only the system's own Rusak asks for a decision about the damaged goods.
 *
 * @phpstan-type ReasonInput array{name: string, is_active?: bool}
 */
class OpnameReasonService
{
    /**
     * @return Collection<int, OpnameReason>
     */
    public function all(): Collection
    {
        return OpnameReason::query()->orderByDesc('is_system')->orderBy('id')->get();
    }

    public function create(string $name): OpnameReason
    {
        return OpnameReason::query()->create(['name' => trim($name), 'behavior' => OpnameReasonBehavior::Plain, 'is_system' => false, 'is_active' => true]);
    }

    /**
     * @param  ReasonInput  $reasonInput
     */
    public function update(OpnameReason $reason, array $reasonInput): OpnameReason
    {
        $reason->forceFill([
            'name' => trim($reasonInput['name']),
            'is_active' => $reasonInput['is_active'] ?? $reason->is_active,
        ])->save();

        return $reason;
    }
}

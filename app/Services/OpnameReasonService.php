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

    public function create(string $name, bool $isActive = true): OpnameReason
    {
        return OpnameReason::query()->create(['name' => trim($name), 'behavior' => OpnameReasonBehavior::Plain, 'is_system' => false, 'is_active' => $isActive]);
    }

    /**
     * Brings the reasons to what the settings page sent: a row with an id is renamed or switched, a row without one is
     * added. A reason left out of the rows is left as it is, since a reason is never deleted.
     *
     * @param  list<array{id?: int|null, name: string, is_active: bool}>  $rows
     */
    public function sync(array $rows): void
    {
        foreach ($rows as $row) {
            if (filled($row['id'] ?? null)) {
                $this->update(OpnameReason::query()->findOrFail($row['id']), ['name' => $row['name'], 'is_active' => (bool) $row['is_active']]);

                continue;
            }

            $this->create($row['name'], (bool) $row['is_active']);
        }
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

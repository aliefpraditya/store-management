<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One thing a store user may do. The rows mirror the {@see \App\Enums\Permission} enum, which the code checks; they are
 * not edited by hand, so they are not audited either.
 */
class Permission extends Model
{
    use CentralConnection;

    protected $guarded = [];

    /**
     * Every permission, grouped by screen area in the order of the enum, for the checkbox lists of the role and user forms.
     *
     * @return list<array{group: string, permissions: list<array{name: string, label: string}>}>
     */
    public static function groupedForForm(): array
    {
        return self::query()
            ->orderBy('id')
            ->get(['name', 'label', 'group'])
            ->groupBy('group')
            ->map(fn ($permissions, string $group): array => [
                'group' => $group,
                'permissions' => $permissions->map(fn (self $permission): array => ['name' => $permission->name, 'label' => $permission->label])->all(),
            ])
            ->values()
            ->all();
    }
}

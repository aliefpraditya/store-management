<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Creates and changes user accounts together with their store assignment, role, and the permissions given to them on top
 * of the role.
 *
 * A super admin has no store; every other user belongs to exactly one store (analisis-sistem 7.0).
 *
 * @phpstan-type UserAttributes array{name: string, username: string, password?: string|null, role: string, store_id?: int|null, permissions?: list<string>, is_active: bool}
 * @phpstan-type Access array{store_id: int, role: string, permissions: list<string>}|array{}
 */
class UserService
{
    /**
     * @param  UserAttributes  $userAttributes
     */
    public function create(array $userAttributes): User
    {
        return DB::transaction(function () use ($userAttributes): User {
            $user = new User([
                'name' => $userAttributes['name'],
                'username' => $userAttributes['username'],
                'password' => $userAttributes['password'],
            ]);
            $user->forceFill([
                'is_super_admin' => $userAttributes['role'] === UserRole::SuperAdmin->value,
                'is_active' => $userAttributes['is_active'],
            ])->save();

            $this->assignAccess($user, $userAttributes);

            return $user;
        });
    }

    /**
     * A blank password keeps the current one.
     *
     * @param  UserAttributes  $userAttributes
     */
    public function update(User $user, array $userAttributes): User
    {
        return DB::transaction(function () use ($user, $userAttributes): User {
            $user->fill(['name' => $userAttributes['name'], 'username' => $userAttributes['username']]);

            if (filled($userAttributes['password'] ?? null)) {
                $user->password = $userAttributes['password'];
            }

            $user->forceFill([
                'is_super_admin' => $userAttributes['role'] === UserRole::SuperAdmin->value,
                'is_active' => $userAttributes['is_active'],
            ])->save();

            $this->assignAccess($user, $userAttributes);

            return $user;
        });
    }

    public function setActive(User $user, bool $isActive): void
    {
        $user->forceFill(['is_active' => $isActive])->save();
    }

    /**
     * A super admin holds every permission already, so they get no store, role, or extra permissions.
     *
     * @param  UserAttributes  $userAttributes
     */
    private function assignAccess(User $user, array $userAttributes): void
    {
        $previousAccess = $this->currentAccessOf($user);

        if ($userAttributes['role'] === UserRole::SuperAdmin->value) {
            $user->stores()->detach();
            $user->permissions()->detach();
        } else {
            $user->stores()->sync([$userAttributes['store_id'] => ['role_id' => Role::query()->where('name', $userAttributes['role'])->value('id')]]);
            $user->permissions()->sync(Permission::query()->whereIn('name', $userAttributes['permissions'] ?? [])->pluck('id'));
        }

        $this->auditAccessChange($user, $previousAccess, $this->currentAccessOf($user));
    }

    /**
     * @return Access
     */
    private function currentAccessOf(User $user): array
    {
        $store = $user->stores()->first();

        if ($store === null) {
            return [];
        }

        return [
            'store_id' => $store->id,
            'role' => (string) Role::query()->whereKey($store->pivot->role_id)->value('name'),
            'permissions' => $user->permissions()->orderBy('name')->pluck('name')->all(),
        ];
    }

    /**
     * Written by hand because the package's relation audit (auditSync) only compares related keys, so a
     * cashier promoted to store admin in the same store would leave no record.
     *
     * @param  Access  $previousAccess
     * @param  Access  $currentAccess
     */
    private function auditAccessChange(User $user, array $previousAccess, array $currentAccess): void
    {
        if ($previousAccess === $currentAccess) {
            return;
        }

        $user->auditEvent = 'store_assignment_changed';
        $user->isCustomEvent = true;
        $user->auditCustomOld = ['store_assignment' => $previousAccess];
        $user->auditCustomNew = ['store_assignment' => $currentAccess];

        Event::dispatch(new AuditCustom($user));

        $user->isCustomEvent = false;
        $user->auditCustomOld = $user->auditCustomNew = null;
    }
}

<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Creates and changes user accounts together with their store assignment.
 *
 * A super admin has no store; every other user belongs to exactly one store (analisis-sistem 7.0).
 *
 * @phpstan-type UserAttributes array{name: string, username: string, password?: string|null, role: UserRole, store_id?: int|null, is_active: bool}
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
                'is_super_admin' => $userAttributes['role'] === UserRole::SuperAdmin,
                'is_active' => $userAttributes['is_active'],
            ])->save();

            $this->assignStore($user, $userAttributes['role'], $userAttributes['store_id'] ?? null);

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
                'is_super_admin' => $userAttributes['role'] === UserRole::SuperAdmin,
                'is_active' => $userAttributes['is_active'],
            ])->save();

            $this->assignStore($user, $userAttributes['role'], $userAttributes['store_id'] ?? null);

            return $user;
        });
    }

    public function setActive(User $user, bool $isActive): void
    {
        $user->forceFill(['is_active' => $isActive])->save();
    }

    private function assignStore(User $user, UserRole $role, ?int $storeId): void
    {
        $previousAssignment = $this->currentAssignmentOf($user);

        if ($role->isStoreRole()) {
            $user->stores()->sync([$storeId => ['role' => $role->value]]);
        } else {
            $user->stores()->detach();
        }

        $this->auditAssignmentChange($user, $previousAssignment, $this->currentAssignmentOf($user));
    }

    /**
     * @return array{store_id: int, role: string}|array{}
     */
    private function currentAssignmentOf(User $user): array
    {
        $store = $user->stores()->first();

        return $store === null ? [] : ['store_id' => $store->id, 'role' => $store->pivot->role];
    }

    /**
     * Written by hand because the package's relation audit (auditSync) only compares related keys, so a
     * cashier promoted to store admin in the same store would leave no record.
     *
     * @param  array{store_id: int, role: string}|array{}  $previousAssignment
     * @param  array{store_id: int, role: string}|array{}  $currentAssignment
     */
    private function auditAssignmentChange(User $user, array $previousAssignment, array $currentAssignment): void
    {
        if ($previousAssignment === $currentAssignment) {
            return;
        }

        $user->auditEvent = 'store_assignment_changed';
        $user->isCustomEvent = true;
        $user->auditCustomOld = ['store_assignment' => $previousAssignment];
        $user->auditCustomNew = ['store_assignment' => $currentAssignment];

        Event::dispatch(new AuditCustom($user));

        $user->isCustomEvent = false;
        $user->auditCustomOld = $user->auditCustomNew = null;
    }
}

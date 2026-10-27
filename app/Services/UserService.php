<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates and changes user accounts together with their store assignment.
 *
 * A super admin has no store; every other user belongs to exactly one store (analisis-sistem 7.0).
 *
 * @phpstan-type UserData array{name: string, username: string, password?: string|null, role: UserRole, store_id?: int|null, is_active: bool}
 */
class UserService
{
    /**
     * @param  UserData  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = new User([
                'name' => $data['name'],
                'username' => $data['username'],
                'password' => $data['password'],
            ]);
            $user->forceFill([
                'is_super_admin' => $data['role'] === UserRole::SuperAdmin,
                'is_active' => $data['is_active'],
            ])->save();

            $this->assignStore($user, $data['role'], $data['store_id'] ?? null);

            return $user;
        });
    }

    /**
     * A blank password keeps the current one.
     *
     * @param  UserData  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->fill(['name' => $data['name'], 'username' => $data['username']]);

            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
            }

            $user->forceFill([
                'is_super_admin' => $data['role'] === UserRole::SuperAdmin,
                'is_active' => $data['is_active'],
            ])->save();

            $this->assignStore($user, $data['role'], $data['store_id'] ?? null);

            return $user;
        });
    }

    public function setActive(User $user, bool $isActive): void
    {
        $user->forceFill(['is_active' => $isActive])->save();
    }

    private function assignStore(User $user, UserRole $role, ?int $storeId): void
    {
        if (! $role->isStoreRole()) {
            $user->stores()->detach();

            return;
        }

        $user->stores()->sync([$storeId => ['role' => $role->value]]);
    }
}

<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\RoleException;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates, changes and deletes the roles that store users hold, together with the permissions each one grants.
 *
 * @phpstan-type RoleAttributes array{label: string, permissions: list<string>}
 */
class RoleService
{
    /**
     * @param  RoleAttributes  $roleAttributes
     */
    public function create(array $roleAttributes): Role
    {
        return DB::transaction(function () use ($roleAttributes): Role {
            $role = new Role(['name' => $this->uniqueNameFor($roleAttributes['label']), 'label' => $roleAttributes['label']]);
            $role->save();

            $this->grant($role, $roleAttributes['permissions']);

            return $role;
        });
    }

    /**
     * A system role keeps its name and label (the code and the docs refer to them); only its permissions change.
     *
     * @param  RoleAttributes  $roleAttributes
     */
    public function update(Role $role, array $roleAttributes): Role
    {
        return DB::transaction(function () use ($role, $roleAttributes): Role {
            if (! $role->is_system) {
                $role->update(['label' => $roleAttributes['label']]);
            }

            $this->grant($role, $roleAttributes['permissions']);

            return $role;
        });
    }

    /**
     * @throws RoleException
     */
    public function delete(Role $role): void
    {
        if ($role->is_system) {
            throw RoleException::systemRoleCannotBeDeleted($role->label);
        }

        $userCount = $role->users()->count();

        if ($userCount > 0) {
            throw RoleException::roleHasUsers($role->label, $userCount);
        }

        $role->delete();
    }

    /**
     * Written through auditSync so the audit trail shows which permissions a role gained or lost.
     *
     * @param  list<string>  $permissionNames
     */
    private function grant(Role $role, array $permissionNames): void
    {
        $role->auditSync('permissions', Permission::query()->whereIn('name', $permissionNames)->pluck('id')->all());
    }

    /**
     * The label as a snake_case name; a number is added when another role already has it.
     */
    private function uniqueNameFor(string $label): string
    {
        $baseName = Str::slug($label, '_');
        $name = $baseName;

        for ($suffix = 2; $name === UserRole::SuperAdmin->value || Role::query()->where('name', $name)->exists(); $suffix++) {
            $name = "{$baseName}_{$suffix}";
        }

        return $name;
    }
}

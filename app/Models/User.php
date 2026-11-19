<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission as PermissionName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable(['name', 'username', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements AuditableContract
{
    /** @use HasFactory<UserFactory> */
    use Auditable, CentralConnection, HasApiTokens, HasFactory, Notifiable;

    /** @var list<string> */
    protected array $auditExclude = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Stores this user may enter, with the per-store role on the pivot.
     *
     * @return BelongsToMany<Store, $this>
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_users')->withPivot('role_id')->withTimestamps();
    }

    /**
     * Permissions given to this user on top of what their role holds.
     *
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user');
    }

    /**
     * The single store a non-super-admin works in (null for super admins and unassigned users).
     */
    public function assignedStore(): ?Store
    {
        return $this->stores->first();
    }

    /**
     * The role this user has inside one store (a super admin is a super admin everywhere); null when they have no access
     * to it.
     */
    public function roleIn(Store $store): ?Role
    {
        if ($this->is_super_admin) {
            return Role::superAdmin();
        }

        $roleId = $this->stores()->whereKey($store->getKey())->first()?->pivot->role_id;

        return $roleId === null ? null : Role::query()->find($roleId);
    }

    /**
     * What this user may do in the store: the permissions of their role plus those given to them directly. A super admin
     * may do everything; someone without access to the store, nothing.
     *
     * @return list<string>
     */
    public function permissionNamesIn(Store $store): array
    {
        $role = $this->roleIn($store);

        return $role === null ? [] : $this->permissionNamesWith($role);
    }

    /**
     * Same as {@see permissionNamesIn()} for a role already looked up, so a request does not look it up twice.
     *
     * @return list<string>
     */
    public function permissionNamesWith(Role $role): array
    {
        if ($role->isSuperAdmin()) {
            return PermissionName::values();
        }

        return $role->permissions()->pluck('name')->merge($this->permissions()->pluck('name'))->unique()->values()->all();
    }

    public function hasPermissionIn(Store $store, PermissionName $permission): bool
    {
        return in_array($permission->value, $this->permissionNamesIn($store), true);
    }

    /**
     * A cashier works with the receipts they made; whoever may see the store's receipts works with any of them.
     */
    public function canHandleReceipt(Sale $sale, Store $store): bool
    {
        return $sale->cashier_id === $this->id || $this->hasPermissionIn($store, PermissionName::SalesView);
    }

    /**
     * Null only for a non-super-admin who has no store yet.
     *
     * @return Attribute<Role|null, never>
     */
    protected function role(): Attribute
    {
        return Attribute::get(function (): ?Role {
            if ($this->is_super_admin) {
                return Role::superAdmin();
            }

            $roleId = $this->assignedStore()?->pivot->role_id;

            return $roleId === null ? null : Role::query()->find($roleId);
        });
    }
}

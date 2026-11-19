<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A named set of permissions that a user holds inside their store (`store_users.role_id`). `is_system` roles ship with the
 * app: their permissions can be changed, but they cannot be renamed or deleted.
 */
#[Fillable(['name', 'label'])]
class Role extends Model implements AuditableContract
{
    /** @use HasFactory<RoleFactory> */
    use Auditable, CentralConnection, HasFactory;

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * The users who hold this role in their store.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_users');
    }

    /**
     * Super admin is no row of `roles`: the flag on the user stands for it, so this stand-in only gives it a name to show.
     */
    public static function superAdmin(): self
    {
        return new self(['name' => UserRole::SuperAdmin->value, 'label' => 'Super Admin']);
    }

    public static function idOf(UserRole $role): int
    {
        return (int) self::query()->where('name', $role->value)->value('id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->name === UserRole::SuperAdmin->value;
    }
}

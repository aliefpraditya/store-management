<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
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
        return $this->belongsToMany(Store::class, 'store_users')->withPivot('role')->withTimestamps();
    }

    /**
     * The single store a non-super-admin works in (null for super admins and unassigned users).
     */
    public function assignedStore(): ?Store
    {
        return $this->stores->first();
    }

    /**
     * Null only for a non-super-admin who has no store yet.
     *
     * @return Attribute<UserRole|null, never>
     */
    protected function role(): Attribute
    {
        return Attribute::get(function (): ?UserRole {
            if ($this->is_super_admin) {
                return UserRole::SuperAdmin;
            }

            $storeRole = $this->assignedStore()?->pivot->role;

            return $storeRole === null ? null : UserRole::from($storeRole);
        });
    }
}

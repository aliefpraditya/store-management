<?php

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * One store = one database. The tenant key is the snake_case store code (used in URLs),
 * and the database credentials live in the `db_*` columns (Hostinger: one user per database).
 */
class Store extends BaseTenant implements AuditableContract, TenantWithDatabase
{
    /** @use HasFactory<StoreFactory> */
    use Auditable, HasDatabase, HasFactory;

    protected $table = 'stores';

    /** @var list<string> */
    protected array $auditExclude = ['db_password', 'data'];

    /**
     * Internal tenancy keys are plain `db_name`, `db_username`, `db_password`, `db_host` columns.
     */
    public static function internalPrefix(): string
    {
        return '';
    }

    public function getTenantKeyName(): string
    {
        return 'code';
    }

    /**
     * @return list<string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'code',
            'receipt_prefix',
            'name',
            'address',
            'phone',
            'db_name',
            'db_username',
            'db_password',
            'db_host',
            'status',
            'schema_version',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Host falls back to the central connection (local development); on Hostinger each store stores its own.
     *
     * @return Attribute<string|null, never>
     */
    protected function dbHost(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value ?: $this->centralConnectionSetting('host'),
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function dbUsername(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value ?: $this->centralConnectionSetting('username'),
        );
    }

    /**
     * Stored encrypted; null means "same password as the central connection" (local development only).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function dbPassword(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null
                ? ($this->centralConnectionSetting('password') ?: null)
                : Crypt::decryptString($value),
            set: fn (?string $value): ?string => $value === null ? null : Crypt::encryptString($value),
        );
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_users')->withPivot('role')->withTimestamps();
    }

    /**
     * @return HasMany<StoreProvisioningLog, $this>
     */
    public function provisioningLogs(): HasMany
    {
        return $this->hasMany(StoreProvisioningLog::class);
    }

    private function centralConnectionSetting(string $key): mixed
    {
        return config('database.connections.'.config('tenancy.database.central_connection').'.'.$key);
    }
}

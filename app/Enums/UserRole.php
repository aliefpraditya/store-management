<?php

namespace App\Enums;

/**
 * Super admin is stored as `users.is_super_admin`; the two store roles live on `store_users.role`,
 * so their values here must match that column.
 */
enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case StoreAdmin = 'admin';
    case Cashier = 'cashier';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::StoreAdmin => 'Admin Toko',
            self::Cashier => 'Kasir',
        };
    }

    public function isStoreRole(): bool
    {
        return $this !== self::SuperAdmin;
    }

    /**
     * @return list<array{value: string, label: string, is_store_role: bool}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $role): array => ['value' => $role->value, 'label' => $role->label(), 'is_store_role' => $role->isStoreRole()],
            self::cases(),
        );
    }
}

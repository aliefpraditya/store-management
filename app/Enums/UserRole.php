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

    /**
     * Who decides the cash difference of a shift that this role worked: a cashier is reviewed by the store's admin, a
     * store admin by the Super Admin, and a Super Admin decides their own.
     */
    public function reviewerLabel(): string
    {
        return match ($this) {
            self::Cashier => 'Admin Toko',
            self::StoreAdmin => 'Super Admin',
            self::SuperAdmin => 'Super Admin (Anda sendiri bisa memutuskannya di Rekap Shift)',
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

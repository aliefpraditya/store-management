<?php

namespace App\Enums;

/**
 * Names of the roles the system ships with (`roles.name`). Super admin is not a row of `store_users`: it is the
 * `users.is_super_admin` flag, and holds every permission. The store roles are rows of `roles` whose permissions an
 * admin may change; further roles are made on the Pusat roles page.
 */
enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case StoreAdmin = 'admin';
    case Cashier = 'cashier';
    case WarehouseStaff = 'warehouse';
}

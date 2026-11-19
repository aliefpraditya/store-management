<?php

namespace App\Exceptions;

use Exception;

/**
 * A change to a role that is not allowed. The message is safe to show to a super admin.
 */
class RoleException extends Exception
{
    public static function systemRoleCannotBeDeleted(string $label): self
    {
        return new self("Peran {$label} bawaan sistem, jadi tidak bisa dihapus.");
    }

    public static function roleHasUsers(string $label, int $userCount): self
    {
        return new self("Peran {$label} masih dipakai {$userCount} user. Pindahkan mereka ke peran lain dulu.");
    }
}

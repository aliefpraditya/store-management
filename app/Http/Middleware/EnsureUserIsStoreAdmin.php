<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Store;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For store pages that only admins may use: cashiers work from the cashier screen alone.
 * Runs after `store.access`, so tenancy is initialized and the user is allowed into the store.
 */
class EnsureUserIsStoreAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        /** @var Store|null $store */
        $store = tenant();

        $role = $user !== null && $store !== null ? $user->roleIn($store) : null;

        abort_unless($role === UserRole::SuperAdmin || $role === UserRole::StoreAdmin, 403);

        return $next($request);
    }
}

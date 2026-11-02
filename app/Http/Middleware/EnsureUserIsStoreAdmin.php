<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
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
        /** @var UserRole|null $storeRole */
        $storeRole = $request->attributes->get(EnsureUserCanAccessStore::ROLE_ATTRIBUTE);

        abort_unless($storeRole === UserRole::SuperAdmin || $storeRole === UserRole::StoreAdmin, 403);

        return $next($request);
    }
}

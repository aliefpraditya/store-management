<?php

namespace App\Http\Middleware;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after tenancy is initialized and the user is authenticated: only active users may enter an
 * active store, and only when they are a super admin or are attached to that store.
 *
 * The role found here is kept on the request, so later middleware and controllers need no second
 * `store_users` lookup.
 */
class EnsureUserCanAccessStore
{
    public const ROLE_ATTRIBUTE = 'store_role';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        /** @var Store|null $store */
        $store = tenant();

        abort_unless($user !== null && $store !== null && $user->is_active && $store->status === StoreStatus::Active, 403);

        $storeRole = $user->roleIn($store);

        abort_if($storeRole === null, 403);

        $request->attributes->set(self::ROLE_ATTRIBUTE, $storeRole);

        return $next($request);
    }
}

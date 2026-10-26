<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after tenancy is initialized and the user is authenticated: only active users may enter an
 * active store, and only when they are a super admin or are attached to that store.
 */
class EnsureUserCanAccessStore
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        /** @var Store|null $store */
        $store = tenant();

        abort_unless($user !== null && $store !== null && $user->is_active && $store->status === 'active', 403);

        abort_unless(
            $user->is_super_admin || $user->stores()->whereKey($store->getKey())->exists(),
            403,
        );

        return $next($request);
    }
}

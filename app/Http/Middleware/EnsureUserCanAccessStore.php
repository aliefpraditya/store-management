<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
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
 * The role and permissions found here are kept on the request, so later middleware and controllers need no second
 * lookup.
 */
class EnsureUserCanAccessStore
{
    public const ROLE_ATTRIBUTE = 'store_role';

    public const PERMISSIONS_ATTRIBUTE = 'store_permissions';

    /**
     * Whether the user of a request that went through this middleware holds the permission in this store; for pages and
     * resources that offer an action only to someone who may do it.
     */
    public static function grants(Request $request, Permission $permission): bool
    {
        return in_array($permission->value, $request->attributes->get(self::PERMISSIONS_ATTRIBUTE, []), true);
    }

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
        $request->attributes->set(self::PERMISSIONS_ATTRIBUTE, $user->permissionNamesWith($storeRole));

        return $next($request);
    }
}

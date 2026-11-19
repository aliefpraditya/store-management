<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `permission:a,b` lets through a user who holds at least one of the named permissions. Runs after `store.access`, which
 * works out what the user holds in this store. An unknown name fails loudly (ValueError) instead of locking everyone out
 * unnoticed.
 */
class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissionNames): Response
    {
        /** @var list<string> $granted */
        $granted = $request->attributes->get(EnsureUserCanAccessStore::PERMISSIONS_ATTRIBUTE, []);

        $requiredNames = array_map(fn (string $name): string => Permission::from($name)->value, $permissionNames);

        abort_if(array_intersect($requiredNames, $granted) === [], 403);

        return $next($request);
    }
}

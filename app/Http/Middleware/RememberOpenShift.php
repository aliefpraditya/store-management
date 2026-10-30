<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Models\User;
use App\Services\ShiftService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On every store page, notes in the session whether the user has an open shift in this store (and forgets it
 * when not), so the pages outside a store can tell and {@see EnsureUserHasNoOpenShift} can lock them. It never
 * blocks anything. It also catches a shift left open before the user logged in again.
 */
class RememberOpenShift
{
    public function __construct(private readonly ShiftService $shifts) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null && tenancy()->initialized) {
            /** @var Store $store */
            $store = tenant();

            if ($this->shifts->openShiftOf($user) !== null) {
                $request->session()->put(EnsureUserHasNoOpenShift::SESSION_KEY, $store->code);
            } elseif ($request->session()->get(EnsureUserHasNoOpenShift::SESSION_KEY) === $store->code) {
                $request->session()->forget(EnsureUserHasNoOpenShift::SESSION_KEY);
            }
        }

        return $next($request);
    }
}

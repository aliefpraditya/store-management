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
 * when not), so the pages outside a store can tell and {@see EnsureUserHasNoOpenShift} can lock them. A user can
 * hold a shift in more than one store, so the session keeps a list of store codes. It never blocks anything. It
 * also catches a shift left open before the user logged in again.
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
            $otherCodes = array_values(array_diff((array) $request->session()->get(EnsureUserHasNoOpenShift::SESSION_KEY, []), [$store->code]));
            $rememberedCodes = $this->shifts->openShiftOf($user) !== null ? [...$otherCodes, $store->code] : $otherCodes;

            if ($rememberedCodes === []) {
                $request->session()->forget(EnsureUserHasNoOpenShift::SESSION_KEY);
            } else {
                $request->session()->put(EnsureUserHasNoOpenShift::SESSION_KEY, $rememberedCodes);
            }
        }

        return $next($request);
    }
}

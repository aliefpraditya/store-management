<?php

namespace App\Services;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\User;

/**
 * Decides where a signed-in user lands: super admins go to the central dashboard, everyone else
 * goes straight to their store (single-store MVP, see analisis-sistem 7.1).
 */
class LandingRouteResolver
{
    /**
     * @return string|null null when the user has nowhere to go (inactive, or no active store)
     */
    public function urlFor(User $user): ?string
    {
        if (! $user->is_active) {
            return null;
        }

        if ($user->is_super_admin) {
            return route('central.dashboard');
        }

        $store = $this->firstActiveStoreOf($user);

        return $store === null ? null : route('tenant.dashboard', ['tenant' => $store->code]);
    }

    private function firstActiveStoreOf(User $user): ?Store
    {
        return $user->stores()
            ->where('stores.status', StoreStatus::Active)
            ->orderBy('stores.name')
            ->first();
    }
}

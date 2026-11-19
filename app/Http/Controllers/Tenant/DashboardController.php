<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureUserCanAccessStore;
use App\Models\Store;
use App\Services\StoreDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Whoever may not see the dashboard numbers (a cashier, a warehouse officer) is sent to the first page their permissions
     * open instead.
     */
    public function __invoke(Request $request, StoreDashboardService $dashboard): Response|RedirectResponse
    {
        /** @var Store $store */
        $store = tenant();

        /** @var list<string> $granted */
        $granted = $request->attributes->get(EnsureUserCanAccessStore::PERMISSIONS_ATTRIBUTE);

        if (! EnsureUserCanAccessStore::grants($request, Permission::DashboardView)) {
            $landingPage = collect(Permission::cases())->first(fn (Permission $permission): bool => in_array($permission->value, $granted, true));

            abort_if($landingPage === null, 403);

            return redirect()->route($landingPage->landingRouteName(), ['tenant' => $store->code]);
        }

        return Inertia::render('Tenant/Dashboard', ['summary' => $dashboard->summary(today())]);
    }
}

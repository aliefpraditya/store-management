<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\UserRole;
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
     * Cashiers only work from the cashier screen, so they are sent there and never see the dashboard numbers.
     */
    public function __invoke(Request $request, StoreDashboardService $dashboard): Response|RedirectResponse
    {
        /** @var Store $store */
        $store = tenant();

        if ($request->attributes->get(EnsureUserCanAccessStore::ROLE_ATTRIBUTE) === UserRole::Cashier) {
            return redirect()->route('tenant.cashier.index', ['tenant' => $store->code]);
        }

        return Inertia::render('Tenant/Dashboard', ['summary' => $dashboard->summary(today())]);
    }
}

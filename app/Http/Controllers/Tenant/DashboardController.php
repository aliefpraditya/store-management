<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\StoreDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Cashiers only work from the cashier screen, so they never see the dashboard numbers.
     */
    public function __invoke(Request $request, StoreDashboardService $dashboard): Response
    {
        /** @var Store $store */
        $store = tenant();

        if ($request->user()->roleIn($store) === UserRole::Cashier) {
            return Inertia::render('Tenant/Cashier');
        }

        return Inertia::render('Tenant/Dashboard', ['summary' => $dashboard->summary(today())]);
    }
}

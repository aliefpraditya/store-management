<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        /** @var Store $store */
        $store = tenant();

        return Inertia::render('Tenant/Dashboard', [
            'store' => ['code' => $store->code, 'name' => $store->name],
        ]);
    }
}

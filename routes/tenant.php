<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\DashboardController as TenantDashboardController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\UnitController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

/*
|--------------------------------------------------------------------------
| Store (tenant) routes
|--------------------------------------------------------------------------
|
| Every store has its own database. The store is identified by the snake_case
| store code in the URL: /t/{tenant}/... (e.g. /t/mitra_utama/ping).
| These are web routes (session login, Inertia pages): the user must be logged
| in and allowed into that store.
|
*/

Route::prefix('t/{tenant}')
    ->middleware(['web', InitializeTenancyByPath::class, 'auth', 'store.access'])
    ->group(function () {
        Route::get('/', TenantDashboardController::class)->name('tenant.dashboard');

        Route::middleware('store.admin')->group(function () {
            Route::resource('products', ProductController::class)->except(['show'])->names('tenant.products');
            Route::resource('settings/units', UnitController::class)->only(['index', 'store', 'update', 'destroy'])->names('tenant.units');
        });

        Route::get('ping', function () {
            return response()->json([
                'store' => tenant('code'),
                'database' => DB::connection()->getDatabaseName(),
            ]);
        })->name('tenant.ping');
    });

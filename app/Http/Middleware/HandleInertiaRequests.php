<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only(['name', 'email', 'is_super_admin']),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'store' => fn () => $this->currentStore(),
            'has_open_shift' => fn () => $request->session()->get(EnsureUserHasNoOpenShift::SESSION_KEY, []) !== [],
            'urls' => [
                'home' => route('home'),
                'central_dashboard' => route('central.dashboard'),
                'central_stores' => route('central.stores.index'),
                'central_users' => route('central.users.index'),
                'login' => route('login'),
                'logout' => route('logout'),
            ],
        ];
    }

    /**
     * The store of the current page, for every page under /t/{code}; null on central pages.
     *
     * @return array{code: string, name: string, receipt_prefix: string, dashboard_url: string, products_url: string, units_url: string, stock_opnames_url: string, stock_history_url: string, cashier_url: string, sales_url: string, delivery_notes_url: string, returns_url: string, shifts_url: string}|null
     */
    private function currentStore(): ?array
    {
        if (! tenancy()->initialized) {
            return null;
        }

        /** @var Store $store */
        $store = tenant();

        return [
            'code' => $store->code,
            'name' => $store->name,
            'receipt_prefix' => $store->receipt_prefix,
            'dashboard_url' => route('tenant.dashboard', ['tenant' => $store->code]),
            'products_url' => route('tenant.products.index', ['tenant' => $store->code]),
            'units_url' => route('tenant.units.index', ['tenant' => $store->code]),
            'stock_opnames_url' => route('tenant.stock-opnames.index', ['tenant' => $store->code]),
            'stock_history_url' => route('tenant.stock-history.index', ['tenant' => $store->code]),
            'cashier_url' => route('tenant.cashier.index', ['tenant' => $store->code]),
            'sales_url' => route('tenant.sales.index', ['tenant' => $store->code]),
            'delivery_notes_url' => route('tenant.delivery-notes.index', ['tenant' => $store->code]),
            'returns_url' => route('tenant.returns.index', ['tenant' => $store->code]),
            'shifts_url' => route('tenant.shifts.index', ['tenant' => $store->code]),
        ];
    }
}

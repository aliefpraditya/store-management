<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Enums\SaleReturnStatus;
use App\Models\SaleReturn;
use App\Models\Store;
use App\Services\CodSettlementService;
use App\Services\SaleHistoryService;
use App\Services\StoreSettingService;
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
                // What the user holds in the store of this page, for hiding what they may not use; the server still enforces it.
                'role_label' => fn () => $request->attributes->get(EnsureUserCanAccessStore::ROLE_ATTRIBUTE)?->label,
                'permissions' => fn () => $request->attributes->get(EnsureUserCanAccessStore::PERMISSIONS_ATTRIBUTE, []),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'store' => fn () => $this->currentStore(),
            'menu_badges' => fn () => $this->menuBadges($request),
            'print_targets' => fn () => $this->printTargets(),
            'has_open_shift' => fn () => $request->session()->get(EnsureUserHasNoOpenShift::SESSION_KEY, []) !== [],
            // Not named `urls`: many pages pass a `urls` prop of their own, which would replace this one.
            'app_urls' => [
                'home' => route('home'),
                'central_dashboard' => route('central.dashboard'),
                'central_stores' => route('central.stores.index'),
                'central_users' => route('central.users.index'),
                'central_roles' => route('central.roles.index'),
                'central_settings' => route('central.settings.index'),
                'login' => route('login'),
                'logout' => route('logout'),
            ],
        ];
    }

    /**
     * What waits for someone in the store, as the numbers on the sidebar entries; null on central pages. The delivery
     * notes entry counts the COD sales whose money is not received yet or waits for an approval, and only for those who
     * may receive or approve it: they are the ones who can act on it, and the others are spared the query.
     *
     * The receipt history entry counts the delivered receipts that did not reach the customer yet, for those who see it.
     *
     * @return array{returns: int, delivery_notes: int, sales: int}|null
     */
    private function menuBadges(Request $request): ?array
    {
        if (! tenancy()->initialized) {
            return null;
        }

        $mayHandleCodMoney = EnsureUserCanAccessStore::grants($request, Permission::CodReceive) || EnsureUserCanAccessStore::grants($request, Permission::CodApprove);

        return [
            'returns' => SaleReturn::query()->where('status', SaleReturnStatus::Pending)->count(),
            'delivery_notes' => $mayHandleCodMoney ? app(CodSettlementService::class)->settlementQueueCount() : 0,
            'sales' => EnsureUserCanAccessStore::grants($request, Permission::SalesView) ? app(SaleHistoryService::class)->pendingDeliveryCount() : 0,
        ];
    }

    /**
     * Where the receipt and the delivery note are printed: the printer named for each (its name and page), and whether a
     * print goes straight to it through QZ Tray. Without QZ Tray the name only reminds whoever prints which printer to pick
     * in the print dialog. Null on central pages.
     *
     * @return array{is_qz_tray_enabled: bool, receipt: array{name: string, width_mm: float, height_mm: float|null}|null, delivery_note: array{name: string, width_mm: float, height_mm: float|null}|null}|null
     */
    private function printTargets(): ?array
    {
        if (! tenancy()->initialized) {
            return null;
        }

        $settings = app(StoreSettingService::class);

        return [
            'is_qz_tray_enabled' => $settings->isQzTrayEnabled(),
            'receipt' => $settings->receiptPrintTarget(),
            'delivery_note' => $settings->deliveryNotePrintTarget(),
        ];
    }

    /**
     * The store of the current page, for every page under /t/{code}; null on central pages.
     *
     * @return array{code: string, name: string, receipt_prefix: string, dashboard_url: string, products_url: string, unit_create_url: string, stock_opnames_url: string, stock_history_url: string, cashier_url: string, sales_url: string, delivery_notes_url: string, returns_url: string, shifts_url: string, expenses_url: string, daily_closings_url: string, settings_url: string, reports_url: string, sales_receipts_url: string, sales_products_url: string, sales_busy_url: string, sales_returns_url: string, stock_report_url: string, stock_positions_url: string, restock_url: string, idle_stock_url: string, stock_movements_url: string}|null
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
            'unit_create_url' => route('tenant.units.store', ['tenant' => $store->code]),
            'stock_opnames_url' => route('tenant.stock-opnames.index', ['tenant' => $store->code]),
            'stock_history_url' => route('tenant.stock-history.index', ['tenant' => $store->code]),
            'cashier_url' => route('tenant.cashier.index', ['tenant' => $store->code]),
            'sales_url' => route('tenant.sales.index', ['tenant' => $store->code]),
            'delivery_notes_url' => route('tenant.delivery-notes.index', ['tenant' => $store->code]),
            'returns_url' => route('tenant.returns.index', ['tenant' => $store->code]),
            'shifts_url' => route('tenant.shifts.index', ['tenant' => $store->code]),
            'expenses_url' => route('tenant.expenses.index', ['tenant' => $store->code]),
            'daily_closings_url' => route('tenant.daily-closings.index', ['tenant' => $store->code]),
            'settings_url' => route('tenant.settings.index', ['tenant' => $store->code]),
            'reports_url' => route('tenant.reports.sales', ['tenant' => $store->code]),
            'sales_receipts_url' => route('tenant.reports.sales.receipts', ['tenant' => $store->code]),
            'sales_products_url' => route('tenant.reports.sales.products', ['tenant' => $store->code]),
            'sales_busy_url' => route('tenant.reports.sales.busy', ['tenant' => $store->code]),
            'sales_returns_url' => route('tenant.reports.sales.returns', ['tenant' => $store->code]),
            'stock_report_url' => route('tenant.reports.stock', ['tenant' => $store->code]),
            'stock_positions_url' => route('tenant.reports.stock.positions', ['tenant' => $store->code]),
            'restock_url' => route('tenant.reports.stock.restock', ['tenant' => $store->code]),
            'idle_stock_url' => route('tenant.reports.stock.idle', ['tenant' => $store->code]),
            'stock_movements_url' => route('tenant.reports.stock.movements', ['tenant' => $store->code]),
        ];
    }
}

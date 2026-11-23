<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\SaleFulfillment;
use App\Enums\SaleReturnStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CashShiftResource;
use App\Models\CashShift;
use App\Models\Register;
use App\Models\SaleReturn;
use App\Models\Store;
use App\Models\User;
use App\Services\CashierCatalogService;
use App\Services\CodSaleService;
use App\Services\CodSettlementService;
use App\Services\CustomerOrderService;
use App\Services\SaleHistoryService;
use App\Services\ShiftService;
use App\Services\StoreSettingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The cashier screen: before a shift is open it offers to open one, afterwards it is the till.
 */
class CashierController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly CustomerOrderService $customerOrders,
        private readonly CashierCatalogService $catalog,
        private readonly StoreSettingService $settings,
        private readonly CodSaleService $codSales,
        private readonly CodSettlementService $codSettlements,
        private readonly SaleHistoryService $saleHistory,
    ) {}

    /**
     * What waits on COD sales, for the badge of the COD button: sales whose goods are not delivered yet, and delivered
     * ones whose money is not received (or approved) yet. The money count is only for those who may receive or approve it,
     * since they are the ones who can act on it.
     *
     * @return array{undelivered: int, unreceived: int}
     */
    private function codCounts(User $user, Store $store): array
    {
        $mayHandleMoney = $user->hasPermissionIn($store, Permission::CodReceive) || $user->hasPermissionIn($store, Permission::CodApprove);

        return [
            'undelivered' => $this->codSales->undeliveredCount(),
            'unreceived' => $mayHandleMoney ? $this->codSettlements->settlementQueueCount() : 0,
        ];
    }

    public function index(Request $request): Response
    {
        /** @var Store $store */
        $store = tenant();
        $code = $store->code;
        $shift = $this->shiftService->openShiftOf($request->user());

        return Inertia::render('Tenant/Cashier/Index', [
            'shift' => $shift === null ? null : CashShiftResource::make($shift)->resolve(),
            'summary' => fn () => $shift === null || $shift->status === CashShiftStatus::PendingReview ? null : $this->shiftService->summarizeForCashier($shift),
            'registers' => fn () => $shift === null ? $this->registersToOpen() : [],
            'payment_methods' => PaymentMethod::saleOptions(),
            'fulfillment_options' => SaleFulfillment::options(),
            'last_sale' => fn () => $request->session()->pull('last_sale'),
            'last_order' => fn () => $request->session()->pull('last_order'),
            'best_sellers' => fn () => $shift === null ? [] : $this->catalog->bestSellers($shift->register->warehouse_id),
            'is_receipt_printed_automatically' => $this->settings->isReceiptPrintedAutomatically(),
            'order_counts' => fn () => $this->customerOrders->badgeCounts(),
            'pending_return_count' => fn () => $shift === null ? 0 : SaleReturn::query()->where('cash_shift_id', $shift->id)->where('status', SaleReturnStatus::Pending)->count(),
            'cod_counts' => fn () => $this->codCounts($request->user(), $store),
            'pending_delivery_count' => fn () => $shift === null ? 0 : $this->saleHistory->pendingDeliveryCount($shift),
            'closed_shift' => fn () => $request->session()->pull('closed_shift'),
            'can_leave_to_dashboard' => $request->user()->hasPermissionIn($store, Permission::DashboardView),
            'urls' => [
                'open_shift' => route('tenant.cashier.shifts.store', ['tenant' => $code]),
                'shift_report' => $shift === null ? null : route('tenant.cashier.shifts.report', ['tenant' => $code, 'shift' => $shift->id]),
                'products' => route('tenant.cashier.products', ['tenant' => $code]),
                'sales' => route('tenant.cashier.sales.store', ['tenant' => $code]),
                'dashboard' => route('tenant.dashboard', ['tenant' => $code]),
                'sales_history' => route('tenant.sales.index', ['tenant' => $code]),
                'receipts' => route('tenant.cashier.receipts', ['tenant' => $code]),
                'return_lookup' => route('tenant.cashier.returns.lookup', ['tenant' => $code]),
                'cod' => route('tenant.cashier.cod.index', ['tenant' => $code]),
                'cod_settlements' => route('tenant.cashier.cod.settlements', ['tenant' => $code]),
                'orders' => route('tenant.cashier.orders.index', ['tenant' => $code]),
                'expenses' => route('tenant.cashier.expenses.index', ['tenant' => $code]),
                'expenses_store' => route('tenant.cashier.expenses.store', ['tenant' => $code]),
            ],
        ]);
    }

    /**
     * A register is busy while a shift on it is open or counted and waiting for review: `busy_with` is that cashier and
     * `busy_note` says which of the two it is.
     *
     * @return list<array{id: int, code: string, name: string, carried_over_cash: string|null, busy_with: string|null, busy_note: string|null}>
     */
    private function registersToOpen(): array
    {
        $heldShifts = CashShift::query()->whereIn('status', [CashShiftStatus::Open, CashShiftStatus::PendingReview])->get()->keyBy('register_id');

        return Register::query()->where('is_active', true)->orderBy('code')->get()
            ->map(function (Register $register) use ($heldShifts): array {
                $heldShift = $heldShifts->get($register->id);

                return [
                    'id' => $register->id,
                    'code' => $register->code,
                    'name' => $register->name,
                    'carried_over_cash' => $this->shiftService->carriedOverCash($register),
                    'busy_with' => $heldShift?->cashier_name,
                    'busy_note' => match ($heldShift?->status) {
                        CashShiftStatus::Open => "dipakai {$heldShift->cashier_name}",
                        CashShiftStatus::PendingReview => "menunggu review, shift {$heldShift->cashier_name}",
                        default => null,
                    },
                ];
            })->all();
    }
}

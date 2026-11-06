<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\CashShiftStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\CashShiftResource;
use App\Models\CashShift;
use App\Models\CustomerOrder;
use App\Models\Register;
use App\Models\Store;
use App\Services\ShiftService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The cashier screen: before a shift is open it offers to open one, afterwards it is the till.
 */
class CashierController extends Controller
{
    public function __construct(private readonly ShiftService $shiftService) {}

    public function index(Request $request): Response
    {
        /** @var Store $store */
        $store = tenant();
        $code = $store->code;
        $shift = $this->shiftService->openShiftOf($request->user());

        return Inertia::render('Tenant/Cashier/Index', [
            'shift' => $shift === null ? null : CashShiftResource::make($shift)->resolve(),
            'summary' => $shift === null ? null : $this->shiftService->summarize($shift),
            'registers' => $shift === null ? $this->registersToOpen() : [],
            'payment_methods' => PaymentMethod::saleOptions(),
            'last_sale' => $request->session()->pull('last_sale'),
            'last_order' => $request->session()->pull('last_order'),
            'open_order_count' => CustomerOrder::query()->whereIn('status', OrderStatus::unfinished())->count(),
            'ready_order_count' => CustomerOrder::query()->where('status', OrderStatus::Ready)->count(),
            'closed_shift' => $request->session()->pull('closed_shift'),
            'can_leave_to_dashboard' => $request->user()->isAdminIn($store),
            'urls' => [
                'open_shift' => route('tenant.cashier.shifts.store', ['tenant' => $code]),
                'products' => route('tenant.cashier.products', ['tenant' => $code]),
                'sales' => route('tenant.cashier.sales.store', ['tenant' => $code]),
                'dashboard' => route('tenant.dashboard', ['tenant' => $code]),
                'sales_history' => route('tenant.sales.index', ['tenant' => $code]),
                'receipts' => route('tenant.cashier.receipts', ['tenant' => $code]),
                'return_lookup' => route('tenant.cashier.returns.lookup', ['tenant' => $code]),
                'cod' => route('tenant.cashier.cod.index', ['tenant' => $code]),
                'orders' => route('tenant.cashier.orders.index', ['tenant' => $code]),
                'expenses' => route('tenant.cashier.expenses.index', ['tenant' => $code]),
                'expenses_store' => route('tenant.cashier.expenses.store', ['tenant' => $code]),
            ],
        ]);
    }

    /**
     * @return list<array{id: int, code: string, name: string, carried_over_cash: string|null, busy_with: string|null}>
     */
    private function registersToOpen(): array
    {
        $openShifts = CashShift::query()->where('status', CashShiftStatus::Open)->pluck('cashier_name', 'register_id');

        return Register::query()->where('is_active', true)->orderBy('code')->get()
            ->map(fn (Register $register): array => [
                'id' => $register->id,
                'code' => $register->code,
                'name' => $register->name,
                'carried_over_cash' => $this->shiftService->carriedOverCash($register),
                'busy_with' => $openShifts[$register->id] ?? null,
            ])->all();
    }
}

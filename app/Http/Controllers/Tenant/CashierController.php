<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\CashShiftResource;
use App\Models\CashShift;
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
    public function __construct(private readonly ShiftService $shifts) {}

    public function index(Request $request): Response
    {
        /** @var Store $store */
        $store = tenant();
        $code = $store->code;
        $shift = $this->shifts->openShiftOf($request->user());

        return Inertia::render('Tenant/Cashier/Index', [
            'shift' => $shift === null ? null : CashShiftResource::make($shift)->resolve(),
            'summary' => $shift === null ? null : $this->shifts->summarize($shift),
            'registers' => $shift === null ? $this->registersToOpen() : [],
            'payment_methods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::forCashier()),
            'last_sale' => $request->session()->pull('last_sale'),
            'closed_shift' => $request->session()->pull('closed_shift'),
            'can_leave_to_dashboard' => in_array($request->user()->roleIn($store), [UserRole::SuperAdmin, UserRole::StoreAdmin], true),
            'urls' => [
                'open_shift' => route('tenant.cashier.shifts.store', ['tenant' => $code]),
                'products' => route('tenant.cashier.products', ['tenant' => $code]),
                'sales' => route('tenant.cashier.sales.store', ['tenant' => $code]),
                'dashboard' => route('tenant.dashboard', ['tenant' => $code]),
            ],
        ]);
    }

    /**
     * @return list<array{id: int, code: string, name: string, fixed_opening_cash: string, busy_with: string|null}>
     */
    private function registersToOpen(): array
    {
        $openShifts = CashShift::query()->where('status', CashShiftStatus::Open)->pluck('cashier_name', 'register_id');

        return Register::query()->where('is_active', true)->orderBy('code')->get()
            ->map(fn (Register $register): array => [
                'id' => $register->id,
                'code' => $register->code,
                'name' => $register->name,
                'fixed_opening_cash' => $register->fixed_opening_cash,
                'busy_with' => $openShifts[$register->id] ?? null,
            ])->all();
    }
}

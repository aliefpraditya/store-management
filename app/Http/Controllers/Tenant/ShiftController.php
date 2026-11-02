<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\ShiftException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CloseShiftRequest;
use App\Http\Requests\Tenant\OpenShiftRequest;
use App\Models\CashShift;
use App\Models\Register;
use App\Models\Store;
use App\Services\ShiftService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class ShiftController extends Controller
{
    public function __construct(private readonly ShiftService $shiftService) {}

    public function store(OpenShiftRequest $request): RedirectResponse
    {
        try {
            $this->shiftService->open($request->user(), Register::query()->findOrFail($request->validated('register_id')));
        } catch (ShiftException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('tenant.cashier.index', ['tenant' => tenant('code')])->with('success', 'Shift dibuka.');
    }

    /**
     * Only the cashier who opened the shift closes it; an admin may close a forgotten one.
     */
    public function close(CloseShiftRequest $request, CashShift $shift): RedirectResponse
    {
        /** @var Store $store */
        $store = tenant();
        $user = $request->user();
        abort_unless($user->isAdminIn($store) || $shift->cashier_id === $user->id, Response::HTTP_FORBIDDEN);

        try {
            $closedShift = $this->shiftService->close($shift, (string) $request->validated('counted_cash'), $request->validated('closing_note'));
        } catch (ShiftException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('tenant.cashier.index', ['tenant' => $store->code])
            ->with('success', 'Shift ditutup.')
            ->with('closed_shift', [
                'expected_cash' => $closedShift->expected_cash,
                'counted_cash' => $closedShift->counted_cash,
                'cash_difference' => $closedShift->cash_difference,
                'deposit_amount' => $closedShift->deposit_amount,
            ]);
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\CashShiftStatus;
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
            $this->shiftService->open(
                $request->user(),
                Register::query()->findOrFail($request->validated('register_id')),
                (string) $request->validated('opening_cash'),
                $request->validated('opening_cash_note'),
            );
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
            $closedShift = $this->shiftService->close(
                $shift,
                (string) $request->validated('counted_cash'),
                $request->validated('closing_note'),
                (string) $request->validated('drawer_left_cash'),
            );
        } catch (ShiftException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $redirect = redirect()->route('tenant.cashier.index', ['tenant' => $store->code]);

        // A shift with a difference stays on the till, locked, until an admin decides: nothing to show as closed yet.
        if ($closedShift->status === CashShiftStatus::PendingReview) {
            return $redirect->with('success', 'Hitungan tercatat. Shift belum ditutup: menunggu review Admin.');
        }

        return $redirect->with('success', 'Shift ditutup.')->with('closed_shift', [
            'counted_cash' => $closedShift->counted_cash,
            'deposit_amount' => $closedShift->deposit_amount,
            'drawer_left_cash' => $closedShift->drawer_left_cash,
        ]);
    }
}

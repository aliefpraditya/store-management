<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\CashShiftStatus;
use App\Exceptions\ShiftException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CloseShiftRequest;
use App\Http\Requests\Tenant\OpenShiftRequest;
use App\Http\Resources\BlindShiftReportResource;
use App\Models\CashShift;
use App\Models\Register;
use App\Models\Store;
use App\Services\ShiftService;
use App\Services\StoreSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
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
                $user,
            );
        } catch (ShiftException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        // An admin closing a shift the cashier forgot works from the recap, and goes back to it.
        if ($closedShift->cashier_id !== $user->id) {
            return redirect()->route('tenant.shifts.show', ['tenant' => $store->code, 'shift' => $closedShift->id])
                ->with('success', $closedShift->status === CashShiftStatus::PendingReview ? 'Hitungan tercatat. Selisihnya menunggu keputusan.' : 'Shift ditutup.');
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
            'report_url' => route('tenant.cashier.shifts.report', ['tenant' => $store->code, 'shift' => $closedShift->id]),
        ]);
    }

    /**
     * The end-of-shift report of the cashier's own shift, printed from a hidden frame once the drawer has been counted.
     * It is the blind version (counts and the cashier's own count only): the Admin prints the full one from the shift
     * recap, so even an admin who was the cashier of this shift gets the blind report here.
     */
    public function report(Request $request, CashShift $shift, StoreSettingService $settings): InertiaResponse
    {
        abort_unless($shift->cashier_id === $request->user()->id, Response::HTTP_FORBIDDEN);
        abort_if($shift->status === CashShiftStatus::Open, Response::HTTP_NOT_FOUND);

        return Inertia::render('Tenant/Print/ShiftReport', [
            'report' => BlindShiftReportResource::make(
                $shift->load('register'),
                $this->shiftService->summarizeForCashier($shift),
                $settings->letterhead(),
                $settings->paperWidth(),
            )->resolve(),
        ]);
    }
}

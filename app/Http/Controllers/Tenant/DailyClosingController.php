<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\DailyClosingStatus;
use App\Exceptions\DailyClosingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CloseDailyRequest;
use App\Http\Requests\Tenant\ReopenDailyClosingRequest;
use App\Http\Resources\DailyClosingResource;
use App\Models\CashShift;
use App\Models\DailyClosing;
use App\Services\DailyClosingService;
use App\Services\SalesDetailReportService;
use App\Services\StoreSettingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Closing the business day (tutup hari) for admins: the checklist and figures of a day, closing it, printing its
 * report, and (Super Admin only) opening a closed day again. Addressed by the day, e.g. `/daily-closings/2026-10-24`.
 */
class DailyClosingController extends Controller
{
    private const DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private readonly DailyClosingService $closingService,
        private readonly SalesDetailReportService $salesDetail,
        private readonly StoreSettingService $settings,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('tenant.daily-closings.show', ['tenant' => tenant('code'), 'date' => today()->toDateString()]);
    }

    public function show(Request $request, string $date): Response
    {
        $day = $this->dayOf($date);
        $closing = $this->closingService->find($day);
        $savedClosing = $closing === null ? null : DailyClosingResource::make($closing)->resolve();
        $isClosed = $closing?->status === DailyClosingStatus::Closed;

        return Inertia::render('Tenant/DailyClosings/Show', [
            'date' => $day->toDateString(),
            'closing' => $savedClosing,
            'checklist' => $this->closingService->checklist($day),
            'figures' => $isClosed ? $savedClosing['figures'] : $this->closingService->summarize($day),
            'voids' => $this->closingService->voidsOf($day),
            'history' => DailyClosingResource::collection($this->closingService->recent())->resolve(),
            'can_reopen' => (bool) $request->user()->is_super_admin,
            'urls' => [
                'index' => route('tenant.daily-closings.index', ['tenant' => tenant('code')]),
                'close' => route('tenant.daily-closings.store', ['tenant' => tenant('code'), 'date' => $day->toDateString()]),
                'reopen' => route('tenant.daily-closings.reopen', ['tenant' => tenant('code'), 'date' => $day->toDateString()]),
                'returns' => route('tenant.returns.index', ['tenant' => tenant('code'), 'status' => 'pending']),
                'delivery_notes' => route('tenant.delivery-notes.index', ['tenant' => tenant('code')]),
            ],
        ]);
    }

    public function store(CloseDailyRequest $request, string $date): RedirectResponse
    {
        try {
            $this->closingService->close($this->dayOf($date), $request->user(), $request->validated('notes'));
        } catch (DailyClosingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Hari ditutup.');
    }

    public function reopen(ReopenDailyClosingRequest $request, string $date): RedirectResponse
    {
        try {
            $this->closingService->reopen($this->dayOf($date), $request->user(), $request->validated('reason'));
        } catch (DailyClosingException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Hari dibuka kembali. Tutup lagi setelah koreksi selesai.');
    }

    /**
     * The daily report, printed from the saved figures in a window of its own. Only a closed day has one. The receipts of
     * the day are read as they stand: a closed day is locked, so they match the saved figures until it is reopened.
     */
    public function sheet(string $date): Response
    {
        $day = $this->dayOf($date);

        return Inertia::render('Tenant/Print/DailyReport', [
            'report' => [
                ...$this->reportOf($this->closedClosingOn($day)),
                'receipts_of_day' => $this->salesDetail->forDay($day),
            ],
        ]);
    }

    /**
     * The daily report for the receipt printer: the figures, the shifts, the voids and the signatures, without the list of
     * every receipt of the day, which only fits the A4 report ({@see sheet()}).
     */
    public function rollSheet(string $date): Response
    {
        return Inertia::render('Tenant/Print/DailyReportRoll', [
            'report' => [
                ...$this->reportOf($this->closedClosingOn($this->dayOf($date))),
                'paper_width' => $this->settings->paperWidth(),
            ],
        ]);
    }

    /**
     * Only a closed day has a report.
     */
    private function closedClosingOn(CarbonImmutable $day): DailyClosing
    {
        $closing = $this->closingService->find($day);

        abort_unless($closing?->status === DailyClosingStatus::Closed, 404);

        return $closing;
    }

    /**
     * What both forms of the daily report hold.
     *
     * @return array<string, mixed>
     */
    private function reportOf(DailyClosing $closing): array
    {
        return [
            ...DailyClosingResource::make($closing)->resolve(),
            'store' => $this->settings->letterhead(),
            'shifts' => $this->shiftRowsOf($closing),
            'voids' => $this->closingService->voidsOf($closing->business_date),
        ];
    }

    /**
     * @return list<array{label: string, register_name: string, cashier_name: string, expected_cash: string|null, counted_cash: string|null, cash_difference: string|null, deposit_amount: string|null}>
     */
    private function shiftRowsOf(DailyClosing $closing): array
    {
        return CashShift::query()
            ->with('register')
            ->where('business_date', $closing->business_date->toDateString())
            ->orderBy('id')
            ->get()
            ->map(fn (CashShift $shift): array => [
                'label' => $shift->label(),
                'register_name' => $shift->register->name,
                'cashier_name' => $shift->cashier_name,
                'expected_cash' => $shift->expected_cash,
                'counted_cash' => $shift->counted_cash,
                'cash_difference' => $shift->cash_difference,
                'deposit_amount' => $shift->deposit_amount,
            ])
            ->all();
    }

    /**
     * The business day in the address. An address that is not a real day, or a day that has not come yet, is not found.
     */
    private function dayOf(string $date): CarbonImmutable
    {
        abort_unless(CarbonImmutable::hasFormat($date, self::DATE_FORMAT), 404);

        $day = CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $date);

        // hasFormat lets a day that does not exist roll over (2026-02-30 becomes 2 March), so the day must read back the same.
        abort_unless($day->format(self::DATE_FORMAT) === $date, 404);
        abort_if($day->toDateString() > today()->toDateString(), 404);

        return $day;
    }
}

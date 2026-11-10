<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\CashShiftStatus;
use App\Enums\ShiftDifferenceStatus;
use App\Exceptions\ShiftException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DateRangeRequest;
use App\Http\Requests\Tenant\DecideShiftDifferenceRequest;
use App\Http\Resources\CashShiftListResource;
use App\Http\Resources\ShiftRecapResource;
use App\Models\CashShift;
use App\Models\SaleReturn;
use App\Services\ExpenseService;
use App\Services\ShiftRecapService;
use App\Services\ShiftService;
use App\Services\StoreSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The shift recap (rekap shift) for admins: every shift with how it ended, and the decision on a cash difference.
 */
class ShiftRecapController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly ShiftRecapService $shiftRecapService,
        private readonly ShiftService $shiftService,
        private readonly ExpenseService $expenseService,
    ) {}

    public function index(DateRangeRequest $request): Response
    {
        $filters = [
            'from' => $request->fromDate()?->toDateString(),
            'to' => $request->toDate()?->toDateString(),
            'status' => $request->string('status')->toString() ?: null,
            'difference' => $request->string('difference')->toString() ?: null,
            'search' => $request->string('search')->trim()->toString() ?: null,
        ];

        return Inertia::render('Tenant/Shifts/Index', [
            'shifts' => $this->shiftRecapService->paginate($filters, self::PER_PAGE)
                ->through(fn (CashShift $shift): array => CashShiftListResource::make($shift)->resolve()),
            'filters' => $filters,
            'statuses' => collect(CashShiftStatus::cases())->map(fn (CashShiftStatus $case): array => ['value' => $case->value, 'label' => $case->label()])->all(),
            'differences' => collect(ShiftDifferenceStatus::cases())->map(fn (ShiftDifferenceStatus $case): array => ['value' => $case->value, 'label' => $case->label()])->all(),
            'pending_difference_count' => $this->shiftRecapService->pendingDifferenceCount(),
        ]);
    }

    public function show(CashShift $shift): Response
    {
        return Inertia::render('Tenant/Shifts/Show', [
            'shift' => [
                ...$this->recapOf($shift),
                'urls' => [
                    'index' => route('tenant.shifts.index', ['tenant' => tenant('code')]),
                    'approve' => route('tenant.shifts.approve', ['tenant' => tenant('code'), 'shift' => $shift->id]),
                    'reject' => route('tenant.shifts.reject', ['tenant' => tenant('code'), 'shift' => $shift->id]),
                    'sheet' => route('tenant.shifts.sheet', ['tenant' => tenant('code'), 'shift' => $shift->id]),
                ],
            ],
        ]);
    }

    /**
     * The end-of-shift report with every figure, for the Admin. A shift still running has none: its expected cash is not
     * for printing before the drawer has been counted.
     */
    public function sheet(CashShift $shift, StoreSettingService $settings): Response
    {
        abort_if($shift->status === CashShiftStatus::Open, HttpResponse::HTTP_NOT_FOUND);

        return Inertia::render('Tenant/Print/ShiftReport', [
            'report' => [
                ...Arr::except($this->recapOf($shift), ['can_decide', 'is_own_shift']),
                'is_blind' => false,
                'store' => $settings->letterhead(),
                'paper_width' => $settings->paperWidth(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function recapOf(CashShift $shift): array
    {
        $shift->load('register');

        $returns = SaleReturn::query()->where('cash_shift_id', $shift->id)->orderBy('id')->get();

        return ShiftRecapResource::make($shift, $this->shiftService->summarize($shift), $returns, $this->expenseService->drawerExpensesOf($shift))->resolve();
    }

    public function approve(DecideShiftDifferenceRequest $request, CashShift $shift): RedirectResponse
    {
        return $this->decide($request, $shift, true, 'Selisih kas disetujui.');
    }

    public function reject(DecideShiftDifferenceRequest $request, CashShift $shift): RedirectResponse
    {
        return $this->decide($request, $shift, false, 'Selisih kas dicatat ditanggung kasir dan shift ditutup. Berita acara dan penggantian uangnya dicatat di luar sistem.');
    }

    private function decide(DecideShiftDifferenceRequest $request, CashShift $shift, bool $isApproved, string $successMessage): RedirectResponse
    {
        try {
            $this->shiftRecapService->decideDifference($shift, $request->user(), $isApproved, $request->validated('note'));
        } catch (ShiftException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $successMessage);
    }
}

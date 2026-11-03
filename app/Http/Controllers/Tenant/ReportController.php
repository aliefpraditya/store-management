<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DateRangeRequest;
use App\Services\SalesReportService;
use App\Services\StoreSettingService;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The store's reports for admins. The sales report shows a period on screen and prints it from a window of its own.
 */
class ReportController extends Controller
{
    /** A report without dates covers the last week, today included. */
    private const DEFAULT_DAYS = 7;

    public function __construct(
        private readonly SalesReportService $salesReport,
        private readonly StoreSettingService $settings,
    ) {}

    public function sales(DateRangeRequest $request): Response
    {
        [$from, $to] = $this->periodOf($request);

        return Inertia::render('Tenant/Reports/Sales', [
            'report' => $this->salesReport->report($from, $to),
            'urls' => [
                'sheet' => route('tenant.reports.sales.sheet', ['tenant' => tenant('code'), 'from' => $from->toDateString(), 'to' => $to->toDateString()]),
            ],
        ]);
    }

    public function salesSheet(DateRangeRequest $request): Response
    {
        [$from, $to] = $this->periodOf($request);

        return Inertia::render('Tenant/Print/SalesReport', [
            'report' => [...$this->salesReport->report($from, $to), 'store' => $this->settings->letterhead()],
        ]);
    }

    /**
     * The period asked for. A start after the end is swapped, and a period longer than the report allows is cut
     * back from its start.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodOf(DateRangeRequest $request): array
    {
        $to = $request->toDate() ?? CarbonImmutable::today();
        $from = $request->fromDate() ?? $to->subDays(self::DEFAULT_DAYS - 1);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $earliest = $to->subDays(SalesReportService::MAX_DAYS - 1);

        return [$from->lessThan($earliest) ? $earliest : $from, $to];
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DateRangeRequest;
use App\Services\SalesReportService;
use App\Services\StoreSettingService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The store's reports for admins. The sales report shows a period on screen and prints it from a window of its own.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly SalesReportService $salesReport,
        private readonly StoreSettingService $settings,
    ) {}

    public function sales(DateRangeRequest $request): Response
    {
        $period = $this->salesReport->period($request->fromDate(), $request->toDate());

        return Inertia::render('Tenant/Reports/Sales', [
            'report' => $this->salesReport->report($period['from'], $period['to']),
            'is_period_cut_back' => $period['is_cut_back'],
            'max_days' => SalesReportService::MAX_DAYS,
            'urls' => [
                'sheet' => route('tenant.reports.sales.sheet', ['tenant' => tenant('code'), 'from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()]),
            ],
        ]);
    }

    public function salesSheet(DateRangeRequest $request): Response
    {
        $period = $this->salesReport->period($request->fromDate(), $request->toDate());

        return Inertia::render('Tenant/Print/SalesReport', [
            'report' => [...$this->salesReport->report($period['from'], $period['to']), 'store' => $this->settings->letterhead()],
        ]);
    }
}

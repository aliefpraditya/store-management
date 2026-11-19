<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DateRangeRequest;
use App\Http\Requests\Tenant\SalesReportRequest;
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

    /**
     * The parts of the report are deferred props, each in a group of its own, so the page shows at once and the parts
     * load side by side; turning a page of the products, or of the groups, asks for that one alone.
     */
    public function sales(SalesReportRequest $request): Response
    {
        $period = $this->salesReport->period($request->fromDate(), $request->toDate());

        return Inertia::render('Tenant/Reports/Sales', [
            'period' => ['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()],
            'summary' => Inertia::defer(fn (): array => $this->salesReport->summary($period['from'], $period['to']), 'summary'),
            'comparison' => Inertia::defer(fn (): array => $this->salesReport->comparison($period['from'], $period['to']), 'comparison'),
            'payments' => Inertia::defer(fn (): array => $this->salesReport->payments($period['from'], $period['to']), 'payments'),
            'products' => Inertia::defer(fn (): array => $this->salesReport->productsPage($period['from'], $period['to'], $request->productsPage(), $request->productsPerPage()), 'products'),
            'groups' => Inertia::defer(fn (): array => $this->salesReport->groupsPage($period['from'], $period['to'], $request->groupsGrouping(), $request->groupsSort(), $request->groupsDirection(), $request->groupsPage(), $request->groupsPerPage()), 'groups'),
            'return_details' => Inertia::defer(fn (): array => $this->salesReport->returnDetails($period['from'], $period['to']), 'return_details'),
            'per_page_options' => SalesReportService::PER_PAGE_OPTIONS,
            'group_per_page_options' => SalesReportService::GROUP_PER_PAGE_OPTIONS,
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

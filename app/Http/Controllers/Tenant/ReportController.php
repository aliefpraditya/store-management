<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\StockStatusFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DateRangeRequest;
use App\Http\Requests\Tenant\SalesReportRequest;
use App\Http\Requests\Tenant\StockReportRequest;
use App\Http\Resources\StockPositionResource;
use App\Models\Brand;
use App\Models\Category;
use App\Services\SalesReportService;
use App\Services\StockReportService;
use App\Services\StoreSettingService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The store's reports for admins. The sales report shows a period on screen and prints it from a window of its own;
 * the stock report shows the stock as it is now.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly SalesReportService $salesReport,
        private readonly StockReportService $stockReport,
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

    /**
     * The three parts of the report are deferred props, each in a group of its own, so the page shows at once and the
     * parts load side by side. Narrowing or turning the table of products asks for that part alone.
     */
    public function stock(StockReportRequest $request): Response
    {
        return Inertia::render('Tenant/Reports/Stock', [
            'filters' => [
                'search' => $request->search(),
                'category' => $request->categoryId(),
                'brand' => $request->brandId(),
                'status' => $request->status()?->value ?? '',
            ],
            'summary' => Inertia::defer(fn (): array => $this->stockReport->summary(), 'summary'),
            'groups' => Inertia::defer(fn (): array => $this->stockReport->groupsPage($request->groupsGrouping(), $request->groupsPage(), $request->groupsPerPage()), 'groups'),
            'positions' => Inertia::defer(fn (): array => $this->positionsPage($request), 'positions'),
            'statuses' => StockStatusFilter::options(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'per_page_options' => StockReportService::PER_PAGE_OPTIONS,
            'group_per_page_options' => StockReportService::GROUP_PER_PAGE_OPTIONS,
        ]);
    }

    public function salesSheet(DateRangeRequest $request): Response
    {
        $period = $this->salesReport->period($request->fromDate(), $request->toDate());

        return Inertia::render('Tenant/Print/SalesReport', [
            'report' => [...$this->salesReport->report($period['from'], $period['to']), 'store' => $this->settings->letterhead()],
        ]);
    }

    /**
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, per_page: int, from: int|null, to: int|null, total: int}
     */
    private function positionsPage(StockReportRequest $request): array
    {
        $positions = $this->stockReport->positionsPage($request->search(), $request->categoryId(), $request->brandId(), $request->status(), $request->positionsPage(), $request->positionsPerPage());

        return [
            'data' => StockPositionResource::collection($positions->items())->resolve(),
            'current_page' => $positions->currentPage(),
            'last_page' => $positions->lastPage(),
            'per_page' => $positions->perPage(),
            'from' => $positions->firstItem(),
            'to' => $positions->lastItem(),
            'total' => $positions->total(),
        ];
    }
}

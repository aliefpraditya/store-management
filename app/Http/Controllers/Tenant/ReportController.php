<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\PaymentMethod;
use App\Enums\StockStatusFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DateRangeRequest;
use App\Http\Requests\Tenant\IdleStockRequest;
use App\Http\Requests\Tenant\RestockRequest;
use App\Http\Requests\Tenant\SalesDetailReportRequest;
use App\Http\Requests\Tenant\SalesReportRequest;
use App\Http\Requests\Tenant\StockMovementRequest;
use App\Http\Requests\Tenant\StockReportRequest;
use App\Http\Resources\IdleStockResource;
use App\Http\Resources\MovedProductResource;
use App\Http\Resources\RestockResource;
use App\Http\Resources\StockPositionResource;
use App\Models\Brand;
use App\Models\Category;
use App\Services\IdleStockService;
use App\Services\RestockReportService;
use App\Services\SalesDetailReportService;
use App\Services\SalesDetailSheetRows;
use App\Services\SalesDetailWorkbook;
use App\Services\SalesReportService;
use App\Services\StockMovementReportService;
use App\Services\StockReportService;
use App\Services\StoreSettingService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The store's reports for admins. The sales report shows a period on screen and prints it from a window of its own;
 * the stock report shows the stock as it is now.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly SalesReportService $salesReport,
        private readonly SalesDetailReportService $salesDetailReport,
        private readonly SalesDetailSheetRows $salesDetailSheets,
        private readonly SalesDetailWorkbook $salesDetailWorkbook,
        private readonly StockReportService $stockReport,
        private readonly IdleStockService $idleStock,
        private readonly RestockReportService $restockReport,
        private readonly StockMovementReportService $stockMovements,
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
            'payment_method_options' => PaymentMethod::saleOptions(),
            'urls' => [
                'sheet' => route('tenant.reports.sales.sheet', ['tenant' => tenant('code'), 'from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()]),
                'detail_sheet' => route('tenant.reports.sales.detail-sheet', ['tenant' => tenant('code'), 'from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()]),
                'detail_export' => route('tenant.reports.sales.detail-export', ['tenant' => tenant('code'), 'from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()]),
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
            'positions' => Inertia::defer(fn (): array => $this->tablePage(
                $this->stockReport->positionsPage($request->search(), $request->categoryId(), $request->brandId(), $request->status(), $request->positionsSort(), $request->positionsDirection(), $request->positionsPage(), $request->positionsPerPage()),
                StockPositionResource::class,
                ['sort' => $request->positionsSort(), 'direction' => $request->positionsDirection()],
            ), 'positions'),
            'statuses' => StockStatusFilter::options(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'per_page_options' => StockReportService::PER_PAGE_OPTIONS,
            'group_per_page_options' => StockReportService::GROUP_PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * The summary and the table are deferred props, each in a group of its own, so the page shows at once and they load
     * side by side. Narrowing or turning the table asks for that part alone.
     */
    public function idle(IdleStockRequest $request): Response
    {
        $days = $request->days();

        return Inertia::render('Tenant/Reports/IdleStock', [
            'filters' => ['days' => $days, 'category' => $request->categoryId()],
            'summary' => Inertia::defer(fn (): array => $this->idleStock->summary($days), 'summary'),
            'products' => Inertia::defer(fn (): array => $this->tablePage(
                $this->idleStock->page($days, $request->categoryId(), $request->page(), $request->perPage()),
                IdleStockResource::class,
            ), 'products'),
            'days_options' => IdleStockService::DAYS_OPTIONS,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'per_page_options' => StockReportService::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * The summary and the table are deferred props, each in a group of its own, so the page shows at once and they load
     * side by side. Narrowing or turning the table asks for that part alone.
     */
    public function restock(RestockRequest $request): Response
    {
        $window = $request->window();
        $cover = $request->cover();

        return Inertia::render('Tenant/Reports/Restock', [
            'filters' => ['window' => $window, 'cover' => $cover, 'category' => $request->categoryId()],
            'summary' => Inertia::defer(fn (): array => $this->restockReport->summary($window, $cover), 'summary'),
            'products' => Inertia::defer(fn (): array => $this->tablePage(
                $this->restockReport->page($window, $cover, $request->categoryId(), $request->sort(), $request->direction(), $request->page(), $request->perPage()),
                RestockResource::class,
                ['sort' => $request->sort(), 'direction' => $request->direction()],
            ), 'products'),
            'window_options' => RestockReportService::WINDOW_OPTIONS,
            'cover_options' => RestockReportService::COVER_OPTIONS,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'per_page_options' => StockReportService::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * The summary and the table are deferred props, each in a group of its own, so the page shows at once and they load
     * side by side. The period is made one the report can give, the same as for the sales report.
     */
    public function movements(StockMovementRequest $request): Response
    {
        $period = $this->salesReport->period($request->fromDate(), $request->toDate());

        return Inertia::render('Tenant/Reports/StockMovements', [
            'period' => ['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()],
            'filters' => ['search' => $request->search(), 'category' => $request->categoryId()],
            'summary' => Inertia::defer(fn (): array => $this->stockMovements->summary($period['from'], $period['to']), 'summary'),
            'products' => Inertia::defer(fn (): array => $this->tablePage(
                $this->stockMovements->page($period['from'], $period['to'], $request->search(), $request->categoryId(), $request->sort(), $request->direction(), $request->page(), $request->perPage()),
                MovedProductResource::class,
                ['sort' => $request->sort(), 'direction' => $request->direction()],
            ), 'products'),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'per_page_options' => StockReportService::PER_PAGE_OPTIONS,
            'is_period_cut_back' => $period['is_cut_back'],
            'max_days' => SalesReportService::MAX_DAYS,
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
     * The same period as the summary sheet, but down to each receipt with its goods and payments.
     */
    public function salesDetailSheet(SalesDetailReportRequest $request): Response
    {
        $period = $this->salesReport->period($request->fromDate(), $request->toDate());

        return Inertia::render('Tenant/Print/SalesDetailReport', [
            'report' => [...$this->salesDetailReport->report($period['from'], $period['to'], $request->paymentMethod()), 'store' => $this->settings->letterhead()],
            'printed_at' => now()->format('Y-m-d H:i'),
        ]);
    }

    /**
     * The detailed report of the period as an xlsx file: the receipts, their goods, the returns and the voids, each a sheet.
     */
    public function salesDetailExport(SalesDetailReportRequest $request): StreamedResponse
    {
        $period = $this->salesReport->period($request->fromDate(), $request->toDate());
        $sheets = $this->salesDetailSheets->sheets($this->salesDetailReport->report($period['from'], $period['to'], $request->paymentMethod()));
        $fileName = $this->exportFileName($this->settings->letterhead()['name'], $period['from'], $period['to']);

        return response()->streamDownload(fn () => $this->salesDetailWorkbook->write($sheets, 'php://output'), $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * `Laporan Penjualan <store> <from> - <to>.xlsx`. Characters a file name cannot hold (a store may well be named with a
     * slash) are dropped, and the dash stands in for "s/d", which could not be written with its slash.
     */
    private function exportFileName(string $storeName, CarbonImmutable $from, CarbonImmutable $to): string
    {
        $safeStoreName = trim(preg_replace('/\s+/', ' ', preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/', ' ', $storeName)));

        return "Laporan Penjualan {$safeStoreName} {$from->toDateString()} - {$to->toDateString()}.xlsx";
    }

    /**
     * One page of a table as the screens read it: its rows through the resource, where it stands among the pages, and,
     * for a table that can be ordered, the order it is in.
     *
     * @param  class-string<JsonResource>  $resource
     * @param  array{sort?: string, direction?: string}  $order
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, per_page: int, from: int|null, to: int|null, total: int, sort?: string, direction?: string}
     */
    private function tablePage(LengthAwarePaginator $paginator, string $resource, array $order = []): array
    {
        return [
            ...$order,
            'data' => $resource::collection($paginator->items())->resolve(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }
}

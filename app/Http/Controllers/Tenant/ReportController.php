<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\PaymentMethod;
use App\Enums\StockStatusFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DateRangeRequest;
use App\Http\Requests\Tenant\IdleStockRequest;
use App\Http\Requests\Tenant\RestockRequest;
use App\Http\Requests\Tenant\SalesDetailReportRequest;
use App\Http\Requests\Tenant\SalesReceiptListRequest;
use App\Http\Requests\Tenant\SalesReportRequest;
use App\Http\Requests\Tenant\SalesReturnsRequest;
use App\Http\Requests\Tenant\StockMovementRequest;
use App\Http\Requests\Tenant\StockReportRequest;
use App\Http\Requests\Tenant\StockSummaryRequest;
use App\Http\Requests\Tenant\StockVarianceRequest;
use App\Http\Resources\IdleStockResource;
use App\Http\Resources\MovedProductResource;
use App\Http\Resources\RestockResource;
use App\Http\Resources\SalesReportReceiptResource;
use App\Http\Resources\StockPositionResource;
use App\Models\Brand;
use App\Models\Category;
use App\Services\IdleStockService;
use App\Services\RestockReportService;
use App\Services\SaleHistoryService;
use App\Services\SalesBusyTimeReportService;
use App\Services\SalesDetailReportService;
use App\Services\SalesDetailSheetRows;
use App\Services\SalesDetailWorkbook;
use App\Services\SalesReportService;
use App\Services\StockMovementReportService;
use App\Services\StockReportService;
use App\Services\StockVarianceReportService;
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
    /** How many products each ranked list of the stock summary holds: the best sellers and the ones that will run out first. */
    private const SUMMARY_LIST_PRODUCTS = 5;

    public function __construct(
        private readonly SalesReportService $salesReport,
        private readonly SalesBusyTimeReportService $salesBusyTime,
        private readonly SalesDetailReportService $salesDetailReport,
        private readonly SalesDetailSheetRows $salesDetailSheets,
        private readonly SalesDetailWorkbook $salesDetailWorkbook,
        private readonly SaleHistoryService $saleHistory,
        private readonly StockReportService $stockReport,
        private readonly IdleStockService $idleStock,
        private readonly RestockReportService $restockReport,
        private readonly StockMovementReportService $stockMovements,
        private readonly StockVarianceReportService $stockVarianceReport,
        private readonly StoreSettingService $settings,
    ) {}

    /**
     * The summary tab: figures and charts. The parts are deferred props, each in a group of its own, so the page shows at
     * once and the parts load side by side; switching the groups between categories and brands asks for that one alone.
     * The products and the groups are only the biggest few ({@see SalesReportService::SUMMARY_TOP_PRODUCTS}, {@see
     * SalesReportService::SUMMARY_TOP_GROUPS}), whatever page or size the address names: the tables to page through are
     * the business of {@see self::salesProducts()}.
     */
    public function sales(SalesReportRequest $request): Response
    {
        $period = $this->salesReport->period($request->fromDate(), $request->toDate());

        return Inertia::render('Tenant/Reports/Sales', [
            'period' => ['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()],
            'summary' => Inertia::defer(fn (): array => $this->salesReport->summary($period['from'], $period['to']), 'summary'),
            'comparison' => Inertia::defer(fn (): array => $this->salesReport->comparison($period['from'], $period['to']), 'comparison'),
            'payments' => Inertia::defer(fn (): array => $this->salesReport->payments($period['from'], $period['to']), 'payments'),
            'products' => Inertia::defer(fn (): array => $this->salesReport->productsPage($period['from'], $period['to'], SalesReportService::PRODUCT_SORTS[0], 'desc', 1, SalesReportService::SUMMARY_TOP_PRODUCTS), 'products'),
            'groups' => Inertia::defer(fn (): array => $this->salesReport->groupsPage($period['from'], $period['to'], $request->groupsGrouping(), SalesReportService::GROUP_SORTS[0], 'desc', 1, SalesReportService::SUMMARY_TOP_GROUPS), 'groups'),
            'is_period_cut_back' => $period['is_cut_back'],
            'max_days' => SalesReportService::MAX_DAYS,
            'urls' => [
                'sheet' => route('tenant.reports.sales.sheet', ['tenant' => tenant('code'), 'from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()]),
            ],
        ]);
    }

    /**
     * The tables of what was sold: the profit per product and the sales per category or brand, each paged and ordered on
     * the server, so the order holds across pages. Both are deferred props, each in a group of its own, so the page shows at once and
     * they load side by side; turning or ordering one asks for that one alone.
     */
    public function salesProducts(SalesReportRequest $request): Response
    {
        $period = $this->salesReport->period($request->fromDate(), $request->toDate());

        return Inertia::render('Tenant/Reports/SalesProducts', [
            'period' => ['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()],
            'products' => Inertia::defer(fn (): array => $this->salesReport->productsPage($period['from'], $period['to'], $request->productsSort(), $request->productsDirection(), $request->productsPage(), $request->productsPerPage()), 'products'),
            'groups' => Inertia::defer(fn (): array => $this->salesReport->groupsPage($period['from'], $period['to'], $request->groupsGrouping(), $request->groupsSort(), $request->groupsDirection(), $request->groupsPage(), $request->groupsPerPage()), 'groups'),
            'per_page_options' => SalesReportService::PER_PAGE_OPTIONS,
            'group_per_page_options' => SalesReportService::GROUP_PER_PAGE_OPTIONS,
            'is_period_cut_back' => $period['is_cut_back'],
            'max_days' => SalesReportService::MAX_DAYS,
        ]);
    }

    /**
     * When the store is busy: the receipts of the period by hour and by weekday, as totals and as averages per trading
     * day. One deferred prop, so the page shows at once and the charts fill in when the server has them.
     */
    public function salesBusy(DateRangeRequest $request): Response
    {
        $period = $this->salesReport->period($request->fromDate(), $request->toDate());

        return Inertia::render('Tenant/Reports/SalesBusy', [
            'period' => ['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()],
            'busy' => Inertia::defer(fn (): array => $this->salesBusyTime->report($period['from'], $period['to']), 'busy'),
            'is_period_cut_back' => $period['is_cut_back'],
            'max_days' => SalesReportService::MAX_DAYS,
        ]);
    }

    /**
     * What came back and what was voided: what became of the returned goods and the products returned most often, and the
     * lists of the approved returns and of the voided receipts with their reasons, each paged and ordered on the server. All four are
     * deferred props, each in a group of its own, so the page shows at once and they load side by side; turning a list asks
     * for that one alone. Who handled a return or a void is not shown, as in the rest of the sales report.
     */
    public function salesReturns(SalesReturnsRequest $request): Response
    {
        $period = $this->salesReport->period($request->fromDate(), $request->toDate());

        return Inertia::render('Tenant/Reports/SalesReturns', [
            'period' => ['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()],
            'return_details' => Inertia::defer(fn (): array => $this->salesReport->returnDetails($period['from'], $period['to']), 'return_details'),
            'returns' => Inertia::defer(fn (): array => $this->salesDetailReport->returnsPage($period['from'], $period['to'], $request->returnsSort(), $request->returnsDirection(), $request->returnsPage(), $request->returnsPerPage()), 'returns'),
            'voids' => Inertia::defer(fn (): array => $this->salesDetailReport->voidsPage($period['from'], $period['to'], $request->voidsSort(), $request->voidsDirection(), $request->voidsPage(), $request->voidsPerPage()), 'voids'),
            'per_page_options' => SalesDetailReportService::LIST_PER_PAGE_OPTIONS,
            'is_period_cut_back' => $period['is_cut_back'],
            'max_days' => SalesReportService::MAX_DAYS,
        ]);
    }

    /**
     * Every receipt of the period, newest day first and grouped by day, each opening to its goods and payments, with the
     * detailed sheet and the spreadsheet to print or save. The summary (with the figures of each day, for the day headings)
     * and the table are deferred props, each in a group of its own, so the page shows at once and they load side by side.
     * Narrowing or turning the table asks for that part alone. A narrowed list has the count and total of each day only,
     * since returns and voids are not tied to a payment method or to a search.
     */
    public function salesReceipts(SalesReceiptListRequest $request): Response
    {
        $period = $this->salesReport->period($request->fromDate(), $request->toDate());
        $method = $request->paymentMethod();
        $filters = [
            'from' => $period['from']->toDateString(),
            'to' => $period['to']->toDateString(),
            'search' => $request->search() ?: null,
            'cashier_id' => null,
            'status' => null,
            'method' => $method?->value,
        ];
        $isNarrowed = $filters['search'] !== null || $filters['method'] !== null;

        return Inertia::render('Tenant/Reports/SalesReceipts', [
            'period' => ['from' => $filters['from'], 'to' => $filters['to']],
            'filters' => ['search' => $request->search(), 'method' => $method?->value ?? ''],
            'summary' => Inertia::defer(fn (): array => [
                ...$this->saleHistory->totals($filters),
                'days' => $isNarrowed ? $this->saleHistory->totalsByDay($filters) : $this->salesReport->dayFigures($period['from'], $period['to']),
            ], 'summary'),
            'receipts' => Inertia::defer(function () use ($filters, $request): array {
                $receipts = $this->saleHistory->paginateWithLines($filters, $request->page(), $request->perPage());

                return [
                    ...$this->tablePage($receipts, SalesReportReceiptResource::class),
                    'continues_previous_day' => $this->saleHistory->continuesPreviousDay($filters, $receipts),
                ];
            }, 'receipts'),
            'payment_method_options' => PaymentMethod::saleOptions(),
            'per_page_options' => SaleHistoryService::RECEIPT_LIST_PER_PAGE_OPTIONS,
            'is_period_cut_back' => $period['is_cut_back'],
            'max_days' => SalesReportService::MAX_DAYS,
            'is_sheet_too_long' => $this->salesDetailReport->exceedsSheetLimit($period['from'], $period['to']),
            'max_sheet_days' => SalesDetailReportService::MAX_SHEET_DAYS,
            'urls' => [
                'detail_sheet' => route('tenant.reports.sales.detail-sheet', ['tenant' => tenant('code'), 'from' => $filters['from'], 'to' => $filters['to']]),
                'detail_export' => route('tenant.reports.sales.detail-export', ['tenant' => tenant('code'), 'from' => $filters['from'], 'to' => $filters['to']]),
            ],
        ]);
    }

    /**
     * The positions tab: the headline figures that narrow the table, and the table of products. Both are deferred props,
     * each in a group of its own, so the page shows at once and they load side by side. Narrowing or turning the table
     * asks for that part alone.
     */
    public function stock(StockReportRequest $request): Response
    {
        $asOf = $request->asOf();

        return Inertia::render('Tenant/Reports/Stock', [
            'filters' => [
                'search' => $request->search(),
                'category' => $request->categoryId(),
                'brand' => $request->brandId(),
                'status' => $request->status()?->value ?? '',
                'as_of' => $asOf?->toDateString(),
            ],
            'first_recorded_on' => $asOf === null ? null : $this->stockReport->ledgerStartsOn(),
            'summary' => Inertia::defer(fn (): array => $this->stockReport->summary($asOf), 'summary'),
            'positions' => Inertia::defer(fn (): array => $this->tablePage(
                $this->stockReport->positionsPage($request->search(), $request->categoryId(), $request->brandId(), $request->status(), $request->positionsSort(), $request->positionsDirection(), $request->positionsPage(), $request->positionsPerPage(), $asOf),
                StockPositionResource::class,
                ['sort' => $request->positionsSort(), 'direction' => $request->positionsDirection()],
            ), 'positions'),
            'statuses' => StockStatusFilter::options(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'per_page_options' => StockReportService::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * The summary tab of the stock report: charts of where the stock's money sits, how healthy the stock is, what sells the
     * most, which products will run out soonest, and how much of the stock does not sell. The parts are deferred props, each in
     * a group of its own, so the page shows at once and they load side by side; switching the groups between categories and
     * brands, between what they are worth and how much there is (in one base unit), or the unit of the best sellers, asks for
     * that part alone. The best sellers, the idle share and the restock figures use the defaults of their own tabs, and the
     * page says so and links there to change them.
     */
    public function stockSummary(StockSummaryRequest $request): Response
    {
        $window = RestockReportService::WINDOW_OPTIONS[0];
        $cover = RestockReportService::COVER_OPTIONS[0];

        return Inertia::render('Tenant/Reports/StockSummary', [
            'summary' => Inertia::defer(fn (): array => $this->stockReport->summary(), 'summary'),
            'groups' => Inertia::defer(fn (): array => $this->stockReport->groupsPage($request->groupsGrouping(), $request->groupsMetric(), $request->groupsUnit(), 1, StockReportService::SUMMARY_TOP_GROUPS), 'groups'),
            'best_sellers' => Inertia::defer(fn (): array => $this->stockReport->bestSellers($window, $request->bestSellersUnit(), self::SUMMARY_LIST_PRODUCTS), 'best_sellers'),
            'idle' => Inertia::defer(fn (): array => $this->idleStock->summary(IdleStockService::DEFAULT_DAYS), 'idle'),
            'restock' => Inertia::defer(fn (): array => $this->restockReport->summary($window, $cover), 'restock'),
            'running_out' => Inertia::defer(fn (): array => $this->restockReport->runningOutSoonest($window, $cover, self::SUMMARY_LIST_PRODUCTS), 'running_out'),
            'idle_days' => IdleStockService::DEFAULT_DAYS,
            'restock_window' => $window,
            'restock_cover' => $cover,
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
     * The summary, the table and the short list of products that are out and not selling are deferred props, each in a group
     * of its own, so the page shows at once and they load side by side. Narrowing or turning a table asks for that part alone.
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
            'idle_out_of_stock' => Inertia::defer(fn (): array => $this->tablePage(
                $this->restockReport->idleOutOfStockPage($window, $cover, $request->categoryId(), $request->idleOutOfStockPage(), RestockReportService::IDLE_OUT_OF_STOCK_PER_PAGE),
                RestockResource::class,
            ), 'idle_out_of_stock'),
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

    /**
     * What the stock counts found missing or extra in a period, in what it was worth: the figures, the shortage per reason and
     * per product, and the list of the counts posted, each paged on the server. All four are deferred props, each in a group of
     * its own, so the page shows at once and they load side by side; turning the list asks for that one alone. The period is
     * made one the report can give, the same as for the sales report.
     */
    public function stockVariances(StockVarianceRequest $request): Response
    {
        $period = $this->salesReport->period($request->fromDate(), $request->toDate());

        return Inertia::render('Tenant/Reports/StockVariances', [
            'period' => ['from' => $period['from']->toDateString(), 'to' => $period['to']->toDateString()],
            'summary' => Inertia::defer(fn (): array => $this->stockVarianceReport->summary($period['from'], $period['to']), 'summary'),
            'reasons' => Inertia::defer(fn (): array => $this->stockVarianceReport->reasons($period['from'], $period['to']), 'reasons'),
            'products' => Inertia::defer(fn (): array => $this->stockVarianceReport->topProducts($period['from'], $period['to'], StockVarianceReportService::TOP_PRODUCTS), 'products'),
            'opnames' => Inertia::defer(fn (): array => $this->opnamesWithUrls($this->stockVarianceReport->opnamesPage($period['from'], $period['to'], $request->opnamesPage(), $request->opnamesPerPage())), 'opnames'),
            'per_page_options' => StockVarianceReportService::OPNAME_PER_PAGE_OPTIONS,
            'is_period_cut_back' => $period['is_cut_back'],
            'max_days' => SalesReportService::MAX_DAYS,
        ]);
    }

    /**
     * A page of counts with the address of each, for the page to link to it.
     *
     * @param  array{data: list<array<string, mixed>>}&array<string, mixed>  $page
     * @return array<string, mixed>
     */
    private function opnamesWithUrls(array $page): array
    {
        $page['data'] = array_map(fn (array $opname): array => [...$opname, 'url' => route('tenant.stock-opnames.show', ['tenant' => tenant('code'), 'stock_opname' => $opname['id']])], $page['data']);

        return $page;
    }

    public function salesSheet(DateRangeRequest $request): Response
    {
        $period = $this->salesReport->period($request->fromDate(), $request->toDate());

        return Inertia::render('Tenant/Print/SalesReport', [
            'report' => [...$this->salesReport->report($period['from'], $period['to']), 'store' => $this->settings->letterhead()],
        ]);
    }

    /**
     * The same period as the summary sheet, but down to each receipt with its goods and payments. The sheet is built in
     * memory and printed from the browser, so it covers at most {@see SalesDetailReportService::MAX_SHEET_DAYS} days; a
     * longer period is refused rather than cut, so a report never quietly lacks days.
     */
    public function salesDetailSheet(SalesDetailReportRequest $request): Response
    {
        $period = $this->salesReport->period($request->fromDate(), $request->toDate());

        abort_if($this->salesDetailReport->exceedsSheetLimit($period['from'], $period['to']), 422, 'Laporan rinci untuk dicetak paling lama '.SalesDetailReportService::MAX_SHEET_DAYS.' hari. Pilih periode yang lebih pendek, atau unduh Excel.');

        return Inertia::render('Tenant/Print/SalesDetailReport', [
            'report' => [...$this->salesDetailReport->report($period['from'], $period['to'], $request->paymentMethod()), 'store' => $this->settings->letterhead()],
            'printed_at' => now()->format('Y-m-d H:i'),
        ]);
    }

    /**
     * The detailed report of the period as an xlsx file: the receipts, their goods, the returns and the voids, each a sheet.
     * The rows are read and written one at a time inside the response, so a whole year (the longest period the report
     * gives) costs little memory; what it costs is time, which is why the limit of the script is lifted for it.
     */
    public function salesDetailExport(SalesDetailReportRequest $request): StreamedResponse
    {
        $period = $this->salesReport->period($request->fromDate(), $request->toDate());
        $method = $request->paymentMethod();
        $fileName = $this->exportFileName($this->settings->letterhead()['name'], $period['from'], $period['to']);

        return response()->streamDownload(function () use ($period, $method): void {
            if (function_exists('set_time_limit')) {
                set_time_limit(300);
            }

            $this->salesDetailWorkbook->write($this->salesDetailSheets->sheets($period['from'], $period['to'], $method), 'php://output');
        }, $fileName, [
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

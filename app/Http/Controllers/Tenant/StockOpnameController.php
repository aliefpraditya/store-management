<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameScope;
use App\Enums\StockOpnameStatus;
use App\Exceptions\StockOpnameException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AddStockOpnameProductRequest;
use App\Http\Requests\Tenant\SaveStockOpnameCountsRequest;
use App\Http\Requests\Tenant\SaveStockOpnameRequest;
use App\Http\Requests\Tenant\SaveStockOpnameReviewRequest;
use App\Http\Resources\StockOpnameItemResource;
use App\Http\Resources\StockOpnameListResource;
use App\Http\Resources\StockOpnameResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OpnameReason;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Warehouse;
use App\Services\OpnameReasonService;
use App\Services\StockOpnameReport;
use App\Services\StockOpnameService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockOpnameController extends Controller
{
    private const LIST_PER_PAGE = 25;

    private const ITEMS_PER_PAGE = 50;

    public function __construct(
        private readonly StockOpnameService $opnameService,
        private readonly StockOpnameReport $opnameReport,
        private readonly OpnameReasonService $opnameReasons,
    ) {}

    public function index(Request $request): Response
    {
        $statusFilter = $request->string('status')->toString();

        $opnameList = StockOpname::query()
            ->with('warehouse')
            ->withCount(['items', 'items as counted_items_count' => fn ($query) => $query->whereNotNull('counted_quantity')])
            ->when(StockOpnameStatus::tryFrom($statusFilter), fn ($query, StockOpnameStatus $status) => $query->where('status', $status))
            ->when($statusFilter === StockOpnameStatus::Cancelled->value, fn ($query) => $query->onlyTrashed())
            ->orderByDesc('opname_date')
            ->orderByDesc('id')
            ->paginate(self::LIST_PER_PAGE)
            ->withQueryString()
            ->through(fn (StockOpname $opname): array => StockOpnameListResource::make($opname)->resolve());

        return Inertia::render('Tenant/StockOpnames/Index', [
            'opnames' => $opnameList,
            'filters' => ['status' => $statusFilter],
            'statuses' => collect(StockOpnameStatus::cases())->map(fn (StockOpnameStatus $case): array => ['value' => $case->value, 'label' => $case->label()])->all(),
            'create_url' => route('tenant.stock-opnames.create', ['tenant' => tenant('code')]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Tenant/StockOpnames/Create', [
            'store_url' => route('tenant.stock-opnames.store', ['tenant' => tenant('code')]),
            'index_url' => route('tenant.stock-opnames.index', ['tenant' => tenant('code')]),
            'warehouses' => Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'type'])
                ->map(fn (Warehouse $warehouse): array => ['id' => $warehouse->id, 'name' => $warehouse->name, 'is_returns' => $warehouse->isReturns()])
                ->all(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'scopes' => collect(StockOpnameScope::cases())->map(fn (StockOpnameScope $case): array => ['value' => $case->value, 'label' => $case->label()])->all(),
            'reasons' => collect(StockOpnameReason::selectable())->map(fn (StockOpnameReason $case): array => ['value' => $case->value, 'label' => $case->label()])->all(),
        ]);
    }

    public function store(SaveStockOpnameRequest $request): RedirectResponse
    {
        $opname = $this->opnameService->create($request->validated(), $request->user()->id);

        return redirect()->route('tenant.stock-opnames.show', ['tenant' => tenant('code'), 'stock_opname' => $opname->id])->with('success', 'Opname dibuat.');
    }

    public function show(Request $request, StockOpname $stockOpname): Response
    {
        $search = $request->string('search')->toString();
        $filter = $request->string('filter')->toString();

        $items = $this->opnameReport->paginateItems($stockOpname, $search, $filter, self::ITEMS_PER_PAGE)
            ->through(fn (StockOpnameItem $item): array => StockOpnameItemResource::make($item)->resolve());

        return Inertia::render('Tenant/StockOpnames/Show', [
            'opname' => StockOpnameResource::make($stockOpname->load(['warehouse', 'category', 'brand', 'creator']))->resolve(),
            'summary' => $this->opnameReport->summarize($stockOpname),
            'damage_summary' => $stockOpname->status === StockOpnameStatus::Review && $stockOpname->reason_code?->requiresVarianceReview() ? $this->opnameReport->damagedGoodsSummary($stockOpname) : null,
            'sealed_pack_drops' => $stockOpname->status === StockOpnameStatus::Review ? $this->opnameReport->sealedPackDrops($stockOpname) : [],
            'items' => $items,
            'filters' => ['search' => $search, 'filter' => $filter],
            'reasons' => $this->opnameReasons->all()->map(fn (OpnameReason $reason): array => [
                'id' => $reason->id,
                'name' => $reason->name,
                'is_active' => $reason->is_active,
                'needs_damage_decision' => $reason->behavior->needsDamageDecision(),
            ])->all(),
        ]);
    }

    public function start(StockOpname $stockOpname): RedirectResponse
    {
        return $this->redirectBackWithOutcome(function () use ($stockOpname): string {
            $skippedCount = $this->opnameService->start($stockOpname);

            return $skippedCount === 0
                ? 'Snapshot stok diambil, silakan mulai menghitung.'
                : "Snapshot stok diambil. {$skippedCount} barang dilewati karena sedang dihitung di opname lain.";
        });
    }

    public function saveCounts(SaveStockOpnameCountsRequest $request, StockOpname $stockOpname): RedirectResponse
    {
        return $this->redirectBackWithOutcome(function () use ($request, $stockOpname): string {
            $this->opnameService->saveCounts($stockOpname, $request->validated('counts'));

            return 'Hitungan disimpan.';
        });
    }

    public function saveReview(SaveStockOpnameReviewRequest $request, StockOpname $stockOpname): RedirectResponse
    {
        return $this->redirectBackWithOutcome(function () use ($request, $stockOpname): string {
            $this->opnameService->saveReview($stockOpname, $request->user()->id, $request->validated('rows'));

            return 'Review disimpan.';
        });
    }

    public function addItem(AddStockOpnameProductRequest $request, StockOpname $stockOpname): RedirectResponse
    {
        return $this->redirectBackWithOutcome(function () use ($request, $stockOpname): string {
            $this->opnameService->addProduct($stockOpname, Product::query()->findOrFail($request->validated('product_id')));

            return 'Barang ditambahkan.';
        });
    }

    public function removeItem(StockOpname $stockOpname, StockOpnameItem $item): RedirectResponse
    {
        return $this->redirectBackWithOutcome(function () use ($stockOpname, $item): string {
            $this->opnameService->removeItem($stockOpname, $item);

            return 'Barang dihapus dari opname.';
        });
    }

    public function countUncountedAsZero(StockOpname $stockOpname): RedirectResponse
    {
        return $this->redirectBackWithOutcome(function () use ($stockOpname): string {
            $this->opnameService->countUncountedAsZero($stockOpname);

            return 'Barang yang belum dihitung ditandai 0.';
        });
    }

    public function finish(StockOpname $stockOpname): RedirectResponse
    {
        return $this->redirectBackWithOutcome(function () use ($stockOpname): string {
            $this->opnameService->finishCounting($stockOpname);

            return 'Hitungan selesai, silakan review selisihnya.';
        });
    }

    public function reopen(StockOpname $stockOpname): RedirectResponse
    {
        return $this->redirectBackWithOutcome(function () use ($stockOpname): string {
            $this->opnameService->reopenCounting($stockOpname);

            return 'Opname dibuka untuk dihitung ulang.';
        });
    }

    public function post(Request $request, StockOpname $stockOpname): RedirectResponse
    {
        return $this->redirectBackWithOutcome(function () use ($request, $stockOpname): string {
            $this->opnameService->post($stockOpname, $request->user()->id);

            return 'Opname diposting, stok sudah diperbarui.';
        });
    }

    public function cancel(StockOpname $stockOpname): RedirectResponse
    {
        try {
            $wasErased = $this->opnameService->cancel($stockOpname);
        } catch (StockOpnameException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('tenant.stock-opnames.index', ['tenant' => tenant('code')])
            ->with('success', $wasErased ? 'Draft opname dihapus.' : 'Opname dibatalkan.');
    }

    /**
     * Runs one opname step and goes back to the page it came from with its message.
     *
     * @param  Closure(): string  $step  returns the success message
     */
    private function redirectBackWithOutcome(Closure $step): RedirectResponse
    {
        try {
            return back()->with('success', $step());
        } catch (StockOpnameException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}

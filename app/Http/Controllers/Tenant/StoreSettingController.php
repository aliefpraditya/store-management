<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveStoreSettingsRequest;
use App\Http\Resources\UnitResource;
use App\Models\OpnameReason;
use App\Models\Store;
use App\Models\Unit;
use App\Services\OpnameReasonService;
use App\Services\StoreSettingService;
use App\Services\UnitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The store settings page for admins: the paper (receipt, delivery note, reports), two rules of the stock opname, the reasons
 * of an opname variance, and the units.
 */
class StoreSettingController extends Controller
{
    public function __construct(
        private readonly StoreSettingService $settings,
        private readonly UnitService $units,
        private readonly OpnameReasonService $opnameReasons,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        /** @var Store $store */
        $store = tenant();
        $unitFilters = $this->units->filtersFrom([
            'search' => $request->query('unit_search'),
            'sort' => $request->query('unit_sort'),
            'direction' => $request->query('unit_direction'),
            'per_page' => $request->query('unit_per_page'),
        ]);
        $unitPage = $this->units->paginate($unitFilters);

        if ($unitPage->isEmpty() && $unitPage->currentPage() > 1) {
            return redirect()->route('tenant.settings.index', ['tenant' => $store->code, ...$request->query(), 'unit_page' => $unitPage->lastPage()]);
        }

        return Inertia::render('Tenant/Settings/Index', [
            'settings' => $this->settings->all(),
            'store_letterhead' => ['name' => $store->name, 'address' => $store->address, 'phone' => $store->phone],
            'paper_widths' => StoreSettingService::PAPER_WIDTHS,
            'units' => $unitPage->through(fn (Unit $unit): array => UnitResource::make($unit)->resolve()),
            'unit_filters' => $unitFilters,
            'unit_per_page_options' => UnitService::PER_PAGE_OPTIONS,
            'opname_reasons' => $this->opnameReasons->all()->map(fn (OpnameReason $reason): array => [
                'id' => $reason->id,
                'name' => $reason->name,
                'is_system' => $reason->is_system,
                'is_active' => $reason->is_active,
                'update_url' => route('tenant.opname-reasons.update', ['tenant' => $store->code, 'opname_reason' => $reason->id]),
            ])->all(),
            'urls' => [
                'update' => route('tenant.settings.update', ['tenant' => $store->code]),
                'unit_create' => route('tenant.units.store', ['tenant' => $store->code]),
                'opname_reason_create' => route('tenant.opname-reasons.store', ['tenant' => $store->code]),
            ],
        ]);
    }

    public function update(SaveStoreSettingsRequest $request): RedirectResponse
    {
        $this->settings->update($request->validated());

        return back()->with('success', 'Pengaturan disimpan.');
    }
}

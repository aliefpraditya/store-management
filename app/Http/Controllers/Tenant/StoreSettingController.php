<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveStoreSettingsRequest;
use App\Http\Resources\UnitResource;
use App\Models\Driver;
use App\Models\OpnameReason;
use App\Models\Store;
use App\Models\Unit;
use App\Services\DriverService;
use App\Services\OpnameReasonService;
use App\Services\SettingsPageService;
use App\Services\StoreSettingService;
use App\Services\UnitService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The store settings page for admins: the paper (receipt, delivery note, reports), two rules of the stock opname, the reasons
 * of an opname variance, the drivers, and the units. Everything but the units is saved with the page's single Save; the
 * units are a list of their own, edited one at a time.
 */
class StoreSettingController extends Controller
{
    public function __construct(
        private readonly StoreSettingService $settings,
        private readonly SettingsPageService $settingsPage,
        private readonly UnitService $units,
        private readonly OpnameReasonService $opnameReasons,
        private readonly DriverService $drivers,
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
            return redirect()->route('tenant.settings.index', [...$request->query(), 'tenant' => $store->code, 'unit_page' => $unitPage->lastPage()]);
        }

        return Inertia::render('Tenant/Settings/Index', [
            'settings' => $this->settings->all(),
            'store_letterhead' => ['name' => $store->name, 'address' => $store->address, 'phone' => $store->phone],
            'paper_widths' => StoreSettingService::PAPER_WIDTHS,
            'units' => $unitPage->through(fn (Unit $unit): array => UnitResource::make($unit)->resolve()),
            'unit_filters' => $unitFilters,
            'unit_per_page_options' => UnitService::PER_PAGE_OPTIONS,
            'drivers' => $this->drivers->all()->map(fn (Driver $driver): array => [
                'id' => $driver->id,
                'name' => $driver->name,
                'is_active' => $driver->is_active,
            ])->all(),
            'opname_reasons' => $this->opnameReasons->all()->map(fn (OpnameReason $reason): array => [
                'id' => $reason->id,
                'name' => $reason->name,
                'is_system' => $reason->is_system,
                'is_active' => $reason->is_active,
            ])->all(),
            'urls' => [
                'update' => route('tenant.settings.update', ['tenant' => $store->code]),
                'unit_create' => route('tenant.units.store', ['tenant' => $store->code]),
            ],
        ]);
    }

    /**
     * The one Save of the page: the settings, the opname reasons and the drivers together. Two names swapped between
     * rows in one save can meet at the database's unique name; that is told rather than saved half way.
     */
    public function update(SaveStoreSettingsRequest $request): RedirectResponse
    {
        try {
            $this->settingsPage->save($request->validated());
        } catch (UniqueConstraintViolationException) {
            return back()->with('error', 'Ada nama yang bentrok saat disimpan, sehingga tidak ada yang tersimpan. Ubah nama satu per satu, lalu simpan lagi.');
        }

        return back()->with('success', 'Pengaturan disimpan.');
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\CashShiftStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveStoreSettingsRequest;
use App\Http\Requests\Tenant\UpdateRegisterRequest;
use App\Http\Resources\UnitResource;
use App\Models\CashShift;
use App\Models\Register;
use App\Models\Store;
use App\Models\Unit;
use App\Services\RegisterService;
use App\Services\StoreSettingService;
use App\Services\UnitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The store settings page for admins: the paper (receipt, delivery note, reports), two rules of the stock opname, the units, and
 * the fixed opening cash of each register.
 */
class StoreSettingController extends Controller
{
    public function __construct(
        private readonly StoreSettingService $settings,
        private readonly RegisterService $registers,
        private readonly UnitService $units,
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
        $busyRegisterIds = CashShift::query()->where('status', CashShiftStatus::Open)->pluck('register_id')->all();

        return Inertia::render('Tenant/Settings/Index', [
            'settings' => $this->settings->all(),
            'store_letterhead' => ['name' => $store->name, 'address' => $store->address, 'phone' => $store->phone],
            'paper_widths' => StoreSettingService::PAPER_WIDTHS,
            'units' => $unitPage->through(fn (Unit $unit): array => UnitResource::make($unit)->resolve()),
            'unit_filters' => $unitFilters,
            'unit_per_page_options' => UnitService::PER_PAGE_OPTIONS,
            'registers' => Register::query()->where('is_active', true)->orderBy('code')->get()->map(fn (Register $register): array => [
                'id' => $register->id,
                'code' => $register->code,
                'name' => $register->name,
                'fixed_opening_cash' => $register->fixed_opening_cash,
                'has_open_shift' => in_array($register->id, $busyRegisterIds, true),
                'update_url' => route('tenant.settings.registers.update', ['tenant' => $store->code, 'register' => $register->id]),
            ])->all(),
            'urls' => [
                'update' => route('tenant.settings.update', ['tenant' => $store->code]),
                'unit_create' => route('tenant.units.store', ['tenant' => $store->code]),
            ],
        ]);
    }

    public function update(SaveStoreSettingsRequest $request): RedirectResponse
    {
        $this->settings->update($request->validated());

        return back()->with('success', 'Pengaturan disimpan.');
    }

    public function updateRegister(UpdateRegisterRequest $request, Register $register): RedirectResponse
    {
        $this->registers->setFixedOpeningCash($register, $request->validated('fixed_opening_cash'));

        return back()->with('success', "Modal kas {$register->name} disimpan. Berlaku untuk shift berikutnya.");
    }
}

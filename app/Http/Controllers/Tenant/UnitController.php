<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\UnitInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveUnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use App\Services\UnitService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Units are managed from the settings page and from the "add unit" window of the item form, so saving
 * redirects back to wherever the request came from.
 */
class UnitController extends Controller
{
    public function __construct(private readonly UnitService $units) {}

    public function index(): Response
    {
        $units = Unit::query()->withCount('productUnits')->orderBy('code')->get();

        return Inertia::render('Tenant/Settings/Units/Index', [
            'units' => UnitResource::collection($units)->resolve(),
            'create_url' => route('tenant.units.store', ['tenant' => tenant('code')]),
        ]);
    }

    public function store(SaveUnitRequest $request): RedirectResponse
    {
        $this->units->create($request->validated());

        return back()->with('success', 'Satuan ditambahkan.');
    }

    public function update(SaveUnitRequest $request, Unit $unit): RedirectResponse
    {
        $this->units->update($unit, $request->validated());

        return back()->with('success', 'Satuan disimpan.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        try {
            $this->units->delete($unit);
        } catch (UnitInUseException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Satuan dihapus.');
    }
}

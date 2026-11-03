<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\UnitInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveUnitRequest;
use App\Models\Unit;
use App\Services\UnitService;
use Illuminate\Http\RedirectResponse;

/**
 * Units are listed on the store settings page and added from the "add unit" window of the item form too, so saving
 * redirects back to wherever the request came from.
 */
class UnitController extends Controller
{
    public function __construct(private readonly UnitService $unitService) {}

    public function store(SaveUnitRequest $request): RedirectResponse
    {
        $this->unitService->create($request->validated());

        return back()->with('success', 'Satuan ditambahkan.');
    }

    public function update(SaveUnitRequest $request, Unit $unit): RedirectResponse
    {
        $this->unitService->update($unit, $request->validated());

        return back()->with('success', 'Satuan disimpan.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        try {
            $this->unitService->delete($unit);
        } catch (UnitInUseException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Satuan dihapus.');
    }
}

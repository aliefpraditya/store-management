<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveDriverRequest;
use App\Models\Driver;
use App\Services\DriverService;
use Illuminate\Http\RedirectResponse;

/**
 * The drivers are managed on the store settings page, so saving goes back to where it came from.
 */
class DriverController extends Controller
{
    public function __construct(private readonly DriverService $drivers) {}

    public function store(SaveDriverRequest $request): RedirectResponse
    {
        $this->drivers->create($request->validated('name'));

        return back()->with('success', 'Sopir ditambahkan.');
    }

    public function update(SaveDriverRequest $request, Driver $driver): RedirectResponse
    {
        $this->drivers->update($driver, $request->validated());

        return back()->with('success', 'Sopir disimpan.');
    }
}

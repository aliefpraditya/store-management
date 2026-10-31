<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\SaleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreSaleRequest;
use App\Http\Resources\SaleReceiptResource;
use App\Services\SaleService;
use App\Services\ShiftService;
use Illuminate\Http\RedirectResponse;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $sales,
        private readonly ShiftService $shifts,
    ) {}

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $shift = $this->shifts->openShiftOf($request->user());

        if ($shift === null) {
            return back()->with('error', SaleException::shiftNotOpen()->getMessage());
        }

        try {
            $sale = $this->sales->create($shift, $request->user(), $request->validated());
        } catch (SaleException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('tenant.cashier.index', ['tenant' => tenant('code')])
            ->with('last_sale', SaleReceiptResource::make($sale)->resolve());
    }
}

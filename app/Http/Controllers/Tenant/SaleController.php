<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\CustomerOrderException;
use App\Exceptions\SaleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreSaleRequest;
use App\Http\Resources\CustomerOrderResource;
use App\Http\Resources\SaleReceiptResource;
use App\Services\CheckoutService;
use App\Services\ShiftService;
use Illuminate\Http\RedirectResponse;

class SaleController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly ShiftService $shiftService,
    ) {}

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $shift = $this->shiftService->openShiftOf($request->user());

        if ($shift === null) {
            return back()->with('error', SaleException::shiftNotOpen()->getMessage());
        }

        try {
            ['sale' => $sale, 'order' => $order] = $this->checkoutService->checkout($shift, $request->user(), $request->validated());
        } catch (SaleException|CustomerOrderException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('tenant.cashier.index', ['tenant' => tenant('code')])
            ->with('last_sale', $sale === null ? null : SaleReceiptResource::make($sale)->resolve())
            ->with('last_order', $order === null ? null : CustomerOrderResource::make($order->load('originSale'))->resolve());
    }
}

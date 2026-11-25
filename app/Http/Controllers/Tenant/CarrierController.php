<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveCarrierRequest;
use App\Http\Resources\CarrierResource;
use App\Models\Carrier;
use App\Services\CarrierService;
use App\Services\RegionSuggestions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The carriers a store's purchases are sent with: the list, and adding and editing one from a window on it.
 */
class CarrierController extends Controller
{
    private const LIST_PER_PAGE = 25;

    public function __construct(
        private readonly CarrierService $carrierService,
        private readonly RegionSuggestions $regionSuggestions,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];

        return Inertia::render('Tenant/Carriers/Index', [
            'carriers' => $this->carrierService->paginate($filters['search'], $filters['status'], self::LIST_PER_PAGE)
                ->through(fn (Carrier $carrier): array => CarrierResource::make($carrier)->resolve()),
            'filters' => $filters,
            'regions' => $this->regionSuggestions->all(),
            'urls' => ['store' => route('tenant.carriers.store', ['tenant' => tenant('code')])],
        ]);
    }

    public function store(SaveCarrierRequest $request): RedirectResponse
    {
        $this->carrierService->create($request->validated());

        return back()->with('success', 'Ekspedisi ditambahkan.');
    }

    public function update(SaveCarrierRequest $request, Carrier $carrier): RedirectResponse
    {
        $this->carrierService->update($carrier, $request->validated());

        return back()->with('success', 'Ekspedisi disimpan.');
    }
}

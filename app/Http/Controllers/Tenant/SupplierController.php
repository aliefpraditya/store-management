<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The suppliers a store buys from: the list, and adding and editing one from a window on it.
 */
class SupplierController extends Controller
{
    private const LIST_PER_PAGE = 25;

    public function __construct(private readonly SupplierService $supplierService) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];

        return Inertia::render('Tenant/Suppliers/Index', [
            'suppliers' => $this->supplierService->paginate($filters['search'], $filters['status'], self::LIST_PER_PAGE)
                ->through(fn (Supplier $supplier): array => SupplierResource::make($supplier)->resolve()),
            'filters' => $filters,
            'urls' => ['store' => route('tenant.suppliers.store', ['tenant' => tenant('code')])],
        ]);
    }

    public function store(SaveSupplierRequest $request): RedirectResponse
    {
        $this->supplierService->create($request->validated());

        return back()->with('success', 'Supplier ditambahkan.');
    }

    public function update(SaveSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->supplierService->update($supplier, $request->validated());

        return back()->with('success', 'Supplier disimpan.');
    }
}

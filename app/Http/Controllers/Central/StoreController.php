<?php

namespace App\Http\Controllers\Central;

use App\Exceptions\StoreProvisioningException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\SaveStoreRequest;
use App\Http\Resources\StoreFormResource;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Models\StoreProvisioningLog;
use App\Services\StoreProvisioner;
use App\Services\StoreService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function __construct(
        private readonly StoreService $stores,
        private readonly StoreProvisioner $provisioner,
    ) {}

    public function index(): Response
    {
        $stores = Store::query()
            ->addSelect(['failure_message' => StoreProvisioningLog::query()
                ->select('message')
                ->whereColumn('store_id', 'stores.id')
                ->where('result', 'failed')
                ->latest('id')
                ->limit(1)])
            ->orderBy('name')
            ->get();

        return Inertia::render('Central/Stores/Index', [
            'stores' => StoreResource::collection($stores)->resolve(),
            'create_url' => route('central.stores.create'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Central/Stores/Form', [
            'store' => null,
            'form_action' => ['url' => route('central.stores.store'), 'method' => 'post'],
        ]);
    }

    /**
     * The store is saved first; if its database cannot be prepared it stays listed as failed so the
     * credentials can be fixed and setup retried.
     */
    public function store(SaveStoreRequest $request): RedirectResponse
    {
        $store = $this->stores->create($request->validated());

        try {
            $this->provisioner->provision($store);
        } catch (StoreProvisioningException $exception) {
            return redirect()->route('central.stores.index')
                ->with('error', "Toko disimpan, tetapi database belum siap. {$exception->getMessage()}");
        }

        return redirect()->route('central.stores.index')->with('success', 'Toko ditambahkan dan siap dipakai.');
    }

    public function edit(Store $store): Response
    {
        return Inertia::render('Central/Stores/Form', [
            'store' => StoreFormResource::make($store)->resolve(),
            'form_action' => ['url' => route('central.stores.update', $store), 'method' => 'put'],
        ]);
    }

    public function update(SaveStoreRequest $request, Store $store): RedirectResponse
    {
        $this->stores->update($store, $request->validated());

        return redirect()->route('central.stores.index')->with('success', 'Toko disimpan.');
    }
}

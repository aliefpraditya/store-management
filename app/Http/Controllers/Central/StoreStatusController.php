<?php

namespace App\Http\Controllers\Central;

use App\Exceptions\StoreStatusException;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\StoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoreStatusController extends Controller
{
    public function __invoke(Request $request, Store $store, StoreService $storeService): RedirectResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        try {
            $storeService->setActive($store, (bool) $validated['is_active']);
        } catch (StoreStatusException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back();
    }
}

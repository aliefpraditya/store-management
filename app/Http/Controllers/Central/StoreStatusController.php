<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\StoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoreStatusController extends Controller
{
    public function __invoke(Request $request, Store $store, StoreService $stores): RedirectResponse
    {
        abort_unless($store->status->canBeToggled(), 403, 'Toko ini belum selesai disiapkan.');

        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        $stores->setActive($store, (bool) $validated['is_active']);

        return back();
    }
}

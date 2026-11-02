<?php

namespace App\Http\Controllers\Central;

use App\Exceptions\StoreProvisioningException;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\StoreProvisioner;
use Illuminate\Http\RedirectResponse;

/**
 * "Coba lagi" for a store whose database setup failed.
 */
class StoreProvisionController extends Controller
{
    public function __invoke(Store $store, StoreProvisioner $storeProvisioner): RedirectResponse
    {
        try {
            $storeProvisioner->provision($store);
        } catch (StoreProvisioningException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Database toko siap dipakai.');
    }
}

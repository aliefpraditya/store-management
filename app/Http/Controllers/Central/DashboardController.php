<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreCardResource;
use App\Models\Store;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Central/Dashboard', [
            'stores' => StoreCardResource::collection(Store::query()->orderBy('name')->get())->resolve(),
        ]);
    }
}

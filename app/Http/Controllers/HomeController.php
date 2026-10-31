<?php

namespace App\Http\Controllers;

use App\Services\LandingRouteResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "/" has no page of its own: it sends everyone to where their role belongs.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, LandingRouteResolver $landing): RedirectResponse
    {
        $user = $request->user();
        $landingUrl = $user === null ? null : $landing->urlFor($user);

        return redirect($landingUrl ?? route('login'));
    }
}

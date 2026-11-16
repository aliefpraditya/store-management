<?php

namespace App\Http\Controllers;

use App\Services\LandingRouteResolver;
use App\Services\SessionTerminator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "/" has no page of its own: it sends everyone to where their role belongs.
 */
class HomeController extends Controller
{
    /**
     * A signed-in user with nowhere to go (deactivated, or their store was deactivated mid-session) is
     * signed out first: the `guest` middleware would bounce them from the login page straight back here.
     */
    public function __invoke(Request $request, LandingRouteResolver $landingRouteResolver, SessionTerminator $sessionTerminator): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $landingUrl = $landingRouteResolver->urlFor($user);

        if ($landingUrl === null) {
            $sessionTerminator->end($request);

            return redirect()->route('login')->withErrors(['username' => LandingRouteResolver::NO_ACCESS_MESSAGE]);
        }

        return redirect($landingUrl);
    }
}

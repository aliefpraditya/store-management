<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LandingRouteResolver;
use App\Services\SessionTerminator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly SessionTerminator $sessionTerminator) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * @throws ValidationException
     */
    public function store(LoginRequest $request, LandingRouteResolver $landingRouteResolver): RedirectResponse
    {
        $request->authenticate();

        $landingUrl = $landingRouteResolver->urlFor($request->user());

        if ($landingUrl === null) {
            $this->sessionTerminator->end($request);

            throw ValidationException::withMessages(['username' => LandingRouteResolver::NO_ACCESS_MESSAGE]);
        }

        $request->session()->regenerate();

        return redirect()->intended($landingUrl);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->sessionTerminator->end($request);

        return redirect()->route('login');
    }
}

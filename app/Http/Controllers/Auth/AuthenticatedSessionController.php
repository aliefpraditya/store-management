<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LandingRouteResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * @throws ValidationException
     */
    public function store(LoginRequest $request, LandingRouteResolver $landing): RedirectResponse
    {
        $request->authenticate();

        $landingUrl = $landing->urlFor($request->user());

        if ($landingUrl === null) {
            $this->endSession($request);

            throw ValidationException::withMessages(['username' => 'Akun ini belum punya akses ke toko mana pun.']);
        }

        $request->session()->regenerate();

        return redirect()->intended($landingUrl);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->endSession($request);

        return redirect()->route('login');
    }

    private function endSession(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}

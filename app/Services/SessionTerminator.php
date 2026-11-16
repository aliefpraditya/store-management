<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Signs the web user out and discards the session; shared by sign-out and by the sign-in/landing
 * paths that must drop a user who has nowhere to go.
 */
class SessionTerminator
{
    public function end(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}

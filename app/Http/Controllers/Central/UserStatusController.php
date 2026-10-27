<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserStatusController extends Controller
{
    public function __invoke(Request $request, User $user, UserService $users): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'Anda tidak bisa menonaktifkan akun sendiri.');

        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        $users->setActive($user, (bool) $validated['is_active']);

        return back();
    }
}

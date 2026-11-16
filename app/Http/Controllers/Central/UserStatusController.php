<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\SetUserActiveRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;

class UserStatusController extends Controller
{
    public function __invoke(SetUserActiveRequest $request, User $user, UserService $userService): RedirectResponse
    {
        $userService->setActive($user, $request->boolean('is_active'));

        return back();
    }
}

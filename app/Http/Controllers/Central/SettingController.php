<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\UpdateSettingsRequest;
use App\Services\AppSettingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The application-wide settings, for the Super Admin.
 */
class SettingController extends Controller
{
    public function __construct(private readonly AppSettingService $appSettingService) {}

    public function index(): Response
    {
        return Inertia::render('Central/Settings/Index', [
            'settings' => ['password_min_length' => $this->appSettingService->passwordMinLength()],
            'limits' => [
                'password_min_length' => [
                    'lowest' => AppSettingService::LOWEST_PASSWORD_MIN_LENGTH,
                    'highest' => AppSettingService::HIGHEST_PASSWORD_MIN_LENGTH,
                ],
            ],
            'update_url' => route('central.settings.update'),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $this->appSettingService->setPasswordMinLength($request->integer('password_min_length'));

        return back()->with('success', 'Pengaturan disimpan.');
    }
}

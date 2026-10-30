<?php

use App\Http\Middleware\EnsureUserCanAccessStore;
use App\Http\Middleware\EnsureUserHasNoOpenShift;
use App\Http\Middleware\EnsureUserIsStoreAdmin;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RememberOpenShift;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'store.access' => EnsureUserCanAccessStore::class,
            'super.admin' => EnsureUserIsSuperAdmin::class,
            'store.admin' => EnsureUserIsStoreAdmin::class,
            'shift.closed' => EnsureUserHasNoOpenShift::class,
            'shift.remember' => RememberOpenShift::class,
        ]);

        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

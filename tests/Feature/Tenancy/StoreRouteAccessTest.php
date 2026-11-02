<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\EnsureUserCanAccessStore;
use App\Http\Middleware\EnsureUserHasNoOpenShift;
use App\Http\Middleware\EnsureUserIsStoreAdmin;
use App\Http\Middleware\RememberOpenShift;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Tests\TestCase;

/**
 * Every page of a store is behind tenancy, login and store access; item pages are for admins only.
 */
class StoreRouteAccessTest extends TestCase
{
    /**
     * @return list<RoutingRoute>
     */
    private function storeRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 't/{tenant}'),
        ));
    }

    public function test_every_store_route_requires_tenancy_login_and_store_access(): void
    {
        $this->assertNotEmpty($this->storeRoutes());

        foreach ($this->storeRoutes() as $route) {
            $middleware = app('router')->gatherRouteMiddleware($route);
            $name = $route->getName() ?? $route->uri();

            $this->assertContains(InitializeTenancyByPath::class, $middleware, "{$name} must initialize tenancy");
            $this->assertContains(Authenticate::class, $middleware, "{$name} must require login");
            $this->assertContains(EnsureUserCanAccessStore::class, $middleware, "{$name} must check store access");
        }
    }

    /**
     * Logout and the central pages are closed while a shift is open; store pages stay open but note the open shift.
     */
    public function test_logout_and_the_central_pages_are_locked_while_a_shift_is_open(): void
    {
        $lockedRoutes = array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RoutingRoute $route): bool => str_starts_with((string) $route->getName(), 'central.') || $route->getName() === 'logout',
        );

        $this->assertNotEmpty($lockedRoutes);

        foreach ($lockedRoutes as $route) {
            $this->assertContains(EnsureUserHasNoOpenShift::class, app('router')->gatherRouteMiddleware($route), "{$route->getName()} must be closed while a shift is open");
        }

        foreach ($this->storeRoutes() as $route) {
            $this->assertNotContains(EnsureUserHasNoOpenShift::class, app('router')->gatherRouteMiddleware($route), "{$route->getName()} stays open during a shift");
            $this->assertContains(RememberOpenShift::class, app('router')->gatherRouteMiddleware($route), "{$route->getName()} must note an open shift");
        }
    }

    public function test_the_cashier_screen_routes_are_open_to_every_user_of_the_store(): void
    {
        $cashierRoutes = array_filter($this->storeRoutes(), fn (RoutingRoute $route): bool => $this->isOpenToCashiers($route));

        $this->assertCount(18, $cashierRoutes);

        foreach ($cashierRoutes as $route) {
            $middleware = app('router')->gatherRouteMiddleware($route);

            $this->assertContains(EnsureUserCanAccessStore::class, $middleware, "{$route->getName()} must check store access");
            $this->assertNotContains(EnsureUserIsStoreAdmin::class, $middleware, "{$route->getName()} is for cashiers too");
        }
    }

    /**
     * The cashier screen, with the receipts of the running shift in its dialog. The receipt history pages are not
     * part of it: they are for admins.
     */
    private function isOpenToCashiers(RoutingRoute $route): bool
    {
        return str_starts_with((string) $route->getName(), 'tenant.cashier.');
    }

    /**
     * Only the dashboard (which sends cashiers to their screen) and the cashier screen are open to cashiers.
     */
    public function test_every_other_store_route_is_for_store_admins_only(): void
    {
        $adminRoutes = array_filter(
            $this->storeRoutes(),
            fn (RoutingRoute $route): bool => $route->getName() !== 'tenant.dashboard' && ! $this->isOpenToCashiers($route),
        );

        $names = array_map(fn (RoutingRoute $route): string => (string) $route->getName(), $adminRoutes);

        $this->assertCount(7, array_filter($names, fn (string $name): bool => str_starts_with($name, 'tenant.products.')));
        $this->assertCount(4, array_filter($names, fn (string $name): bool => str_starts_with($name, 'tenant.units.')));
        $this->assertCount(13, array_filter($names, fn (string $name): bool => str_starts_with($name, 'tenant.stock-opnames.')));
        $this->assertCount(1, array_filter($names, fn (string $name): bool => str_starts_with($name, 'tenant.stock-history.')));
        $this->assertCount(2, array_filter($names, fn (string $name): bool => str_starts_with($name, 'tenant.sales.')));
        $this->assertCount(10, array_filter($names, fn (string $name): bool => str_starts_with($name, 'tenant.delivery-notes.')));
        $this->assertCount(8, array_filter($names, fn (string $name): bool => str_starts_with($name, 'tenant.returns.')));
        $this->assertCount(5, array_filter($names, fn (string $name): bool => str_starts_with($name, 'tenant.shifts.')));
        $this->assertCount(5, array_filter($names, fn (string $name): bool => str_starts_with($name, 'tenant.expenses.')));
        $this->assertCount(55, $adminRoutes);

        foreach ($adminRoutes as $route) {
            $this->assertContains(
                EnsureUserIsStoreAdmin::class,
                app('router')->gatherRouteMiddleware($route),
                "{$route->getName()} must be store admin only",
            );
        }
    }
}

<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\EnsureUserCanAccessStore;
use App\Http\Middleware\EnsureUserIsStoreAdmin;
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
     * Only the dashboard (which shows cashiers their own screen) and the ping check are open to cashiers.
     */
    public function test_every_other_store_route_is_for_store_admins_only(): void
    {
        $adminRoutes = array_filter(
            $this->storeRoutes(),
            fn (RoutingRoute $route): bool => ! in_array($route->getName(), ['tenant.dashboard', 'tenant.ping'], true),
        );

        $names = array_map(fn (RoutingRoute $route): string => (string) $route->getName(), $adminRoutes);

        $this->assertCount(6, array_filter($names, fn (string $name): bool => str_starts_with($name, 'tenant.products.')));
        $this->assertCount(4, array_filter($names, fn (string $name): bool => str_starts_with($name, 'tenant.units.')));
        $this->assertCount(10, $adminRoutes);

        foreach ($adminRoutes as $route) {
            $this->assertContains(
                EnsureUserIsStoreAdmin::class,
                app('router')->gatherRouteMiddleware($route),
                "{$route->getName()} must be store admin only",
            );
        }
    }
}

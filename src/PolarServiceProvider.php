<?php

namespace AkyrosLabs\Polar;

use AkyrosLabs\Polar\Console\ListProductsCommand;
use AkyrosLabs\Polar\Console\SyncSubscriptionsCommand;
use AkyrosLabs\Polar\Http\Middleware\EnsureSubscribed;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PolarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/polar.php', 'polar');

        $this->app->singleton(PolarClient::class, function () {
            return new PolarClient();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/polar.php' => config_path('polar.php'),
        ], 'polar-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'polar-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadRoutes();
        $this->registerMiddleware();
        $this->registerCommands();
        $this->registerBladeDirectives();
    }

    private function loadRoutes(): void
    {
        Route::group([], __DIR__ . '/Http/routes.php');
    }

    private function registerMiddleware(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('subscribed', EnsureSubscribed::class);
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncSubscriptionsCommand::class,
                ListProductsCommand::class,
            ]);
        }
    }

    private function registerBladeDirectives(): void
    {
        // @subscribed / @endsubscribed
        Blade::if('subscribed', function (string $type = 'default', ?string $productId = null) {
            $billable = $this->resolveBillableFromAuth();
            if (!$billable || !method_exists($billable, 'subscribed')) return false;
            return $billable->subscribed($type, $productId);
        });

        // @onPlan('pro') / @endOnPlan
        Blade::if('onPlan', function (string $plan) {
            $billable = $this->resolveBillableFromAuth();
            if (!$billable || !method_exists($billable, 'planName')) return false;
            return $billable->planName() === $plan;
        });

        // @onTrial / @endOnTrial
        Blade::if('onTrial', function (string $type = 'default') {
            $billable = $this->resolveBillableFromAuth();
            if (!$billable || !method_exists($billable, 'onTrial')) return false;
            return $billable->onTrial($type);
        });

        // @feature('advanced_monitoring') / @endFeature
        Blade::if('feature', function (string $feature) {
            $billable = $this->resolveBillableFromAuth();
            if (!$billable || !method_exists($billable, 'hasFeature')) return false;
            return $billable->hasFeature($feature);
        });
    }

    /**
     * Resolve the billable model from the currently authenticated user.
     */
    private function resolveBillableFromAuth(): ?object
    {
        $user = auth()->user();
        if (!$user) return null;

        // If user itself is billable
        if (method_exists($user, 'subscriptions')) {
            return $user;
        }

        // Try tenant / team relationship
        if (method_exists($user, 'currentTeam') && $user->currentTeam) {
            return $user->currentTeam;
        }

        if (method_exists($user, 'tenant') && $user->tenant) {
            return $user->tenant;
        }

        return null;
    }
}

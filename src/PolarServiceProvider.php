<?php

namespace AkyrosLabs\Polar;

use Illuminate\Support\ServiceProvider;

class PolarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/polar.php', 'polar');

        $this->app->singleton(PolarClient::class);
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/polar.php' => config_path('polar.php'),
        ], 'polar-config');

        // Publish migration
        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'polar-migrations');

        // Register webhook route
        $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\SyncSubscriptionsCommand::class,
            ]);
        }
    }
}

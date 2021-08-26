<?php

namespace glasswalllab\arofloconnector;

use Illuminate\Support\ServiceProvider;

class ArofloConnectorServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register the main class to use with the facade
        $this->app->singleton('ArofloConnector', function () {
            return new ArofloConnector;
        });

        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'ArofloConnector');
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
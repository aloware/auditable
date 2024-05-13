<?php

namespace Aloware\Auditable;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

class ServiceProvider extends LaravelServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/auditable.php' => config_path('auditable.php')
        ], 'config');

        $this->loadRoutesFrom(
            realpath(__DIR__ . '/../routes/api.php')
        );

        $this->loadMigrationsFrom(
            __DIR__ . '/../database/migrations'
        );
    }
}
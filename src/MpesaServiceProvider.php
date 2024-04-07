<?php

namespace Akika\LaravelMpesa;

use Illuminate\Support\ServiceProvider;

class MpesaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('mpesa', function () {
            return new Mpesa();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Load package migrations
        if ($this->app->runningInConsole()) {
            // Register InstallAkikaMpesaPackage command
            $this->commands([
                Commands\InstallAkikaMpesaPackage::class,
            ]);

            // Register migrations
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

            /// Register routes
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

            $this->publishes([
                __DIR__ . '/../config/mpesa.php' => config_path('mpesa.php')
            ], 'config');
        }
    }
}

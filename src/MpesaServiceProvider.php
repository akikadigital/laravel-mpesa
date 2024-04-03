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
            // Register PublishMpesaMigrations command
            $this->commands([
                Commands\PublishMpesaMigrations::class,
            ]);

            // Register migrations
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

            $this->publishes([
                __DIR__ . '/../config/mpesa.php' => config_path('mpesa.php')
            ], 'config');
        }
    }
}

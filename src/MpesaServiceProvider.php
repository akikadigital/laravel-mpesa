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

            // Publish the mpesa config file
            $this->publishes([
                __DIR__ . '/../config/mpesa.php' => config_path('mpesa.php')
            ], 'config'); // Register InstallAkikaMpesaPackage command

            // Register InstallAkikaMpesaPackage command
            $this->commands([
                Commands\InstallAkikaMpesaPackage::class,
            ]);
        }
    }
}

<?php

namespace Akika\LaravelMpesa\Commands;

use Illuminate\Console\Command;

class InstallAkikaMpesaPackage extends Command
{
    protected $signature = 'mpesa:install';

    protected $description = 'Publish Akika/LaravelMpesa package migrations';

    public function handle()
    {
        // check if the config file exists
        if (!$this->checkIfConfigExists()) {
            $this->publishConfig();
        } else {
            // get confirmation from user to overwrite existing config file
            if ($this->getForceConcentForConfig()) {
                $this->publishConfig(true);
            } else {
                $this->info('Publishing Akika/LaravelMpesa package config file cancelled.');
            }
        }
    }

    /**
     * Check if the config file exists
     * @return bool
     */

    public function checkIfConfigExists()
    {
        $this->info('Checking if Akika/LaravelMpesa package config file exists...');

        // check if the config file exists
        if (file_exists(config_path('mpesa.php'))) {
            $this->info('Akika/LaravelMpesa package config file already exists.');
            return true;
        } else {
            $this->info('Akika/LaravelMpesa package config file does not exist.');
            return false;
        }
    }

    /**
     * Publish the config file
     * @param bool $forcePublish
     * @return void
     */

    public function publishConfig($forcePublish = false)
    {
        $this->info('Publishing Akika/LaravelMpesa package config file...');

        $params = [
            '--provider' => "Akika\LaravelMpesa\MpesaServiceProvider",
            '--tag' => "config"
        ];

        if ($forcePublish) {
            $params['--force'] = true;
        }

        $this->call('vendor:publish', $params);

        $this->info('Akika/LaravelMpesa package config file published successfully.');
    }

    /**
     * Get confirmation from user to overwrite existing config file
     * @return bool
     */

    public function getForceConcentForConfig()
    {
        return $this->confirm('Do you want to overwrite existing config file?');
    }
}

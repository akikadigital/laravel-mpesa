<?php

namespace Akika\LaravelMpesa\Commands;

use Illuminate\Console\Command;

class PublishMpesaMigrations extends Command
{
    protected $signature = 'mpesa:install';

    protected $description = 'Publish Akika/LaravelMpesa package migrations';

    public function handle()
    {
        if (!$this->checkIfMigrationsExist()) {
            $this->publishMigrations();
        } else {
            // get confirmation from user to overwrite existing migrations
            if ($this->getForceConcentForMigrations()) {
                $this->publishMigrations();
            } else {
                $this->info('Publishing Akika/LaravelMpesa package migrations cancelled.');
            }
        }

        // check if the config file exists
        if (!$this->checkIfConfigExists()) {
            $this->publishConfig();
        } else {
            // get confirmation from user to overwrite existing config file
            if ($this->getForceConcentForConfig()) {
                $this->publishConfig();
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

    /**
     * Check if the migrations exist
     * @return bool
     */

    public function checkIfMigrationsExist()
    {
        $this->info('Checking if Akika/LaravelMpesa package migrations exist...');

        // scan and get all migration files
        $files = scandir(database_path('migrations'));

        // check if the migration file ....create_tokens_table.php exists
        $migrations = array_filter($files, function ($file) {
            return strpos($file, 'create_tokens_table.php') !== false;
        });

        // return true if migration file exists
        if (count($migrations) > 0) {
            $this->info('Akika/LaravelMpesa package migrations already exist.');
            return true;
        } else {
            $this->info('Akika/LaravelMpesa package migrations do not exist.');
            return false;
        }
    }

    /**
     * Publish the migrations
     * @param bool $forcePublish
     * @return void
     */

    public function publishMigrations($forcePublish = false)
    {
        $this->info('Publishing Akika/LaravelMpesa package migrations...');

        $params = [
            '--provider' => "Akika\LaravelMpesa\MpesaServiceProvider",
            '--tag' => "migrations"
        ];

        if ($forcePublish) {
            $params['--force'] = true;
        }

        $this->call('vendor:publish', $params);

        $this->info('Akika/LaravelMpesa package migrations published successfully.');
    }

    /**
     * Get confirmation from user to overwrite existing migrations
     * @return bool
     */

    public function getForceConcentForMigrations()
    {
        return $this->confirm('Do you want to overwrite existing migrations?');
    }
}

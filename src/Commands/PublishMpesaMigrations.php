<?php

namespace Akika\LaravelMpesa\Commands;

use Illuminate\Console\Command;

class PublishMpesaMigrations extends Command
{
    protected $signature = 'laravel-mpesa:publish-migrations';

    protected $description = 'Publish Mpesa package migrations';

    public function handle()
    {
        $this->info('Publishing Mpesa package migrations...');

        $this->call('vendor:publish', [
            '--provider' => 'Akika\LaravelMpesa\MpesaServiceProvider',
            '--tag' => 'migrations'
        ]);

        $this->info('Mpesa package migrations published successfully.');
    }
}
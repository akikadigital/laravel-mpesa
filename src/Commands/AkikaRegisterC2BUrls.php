<?php

namespace Akika\LaravelMpesa\Commands;

use Akika\LaravelMpesa\Mpesa;
use Illuminate\Console\Command;

class AkikaRegisterC2BUrls extends Command
{
    protected $signature = 'mpesa:register-c2b-urls';

    protected $description = 'Register C2B URLs';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Registering C2B URLs...');

        /**
         * Checking if mpesa shortcode is set in the config file...
         */
        if (!config('mpesa.shortcode')) {
            $this->error('Shortcode is not set in the config file.');
            return;
        }

        /**
         * Checking if STK Confirmation URL is set in the config file...
         */
        if (!config('mpesa.stk_confirmation_url')) {
            $this->error('STK Confirmation URL is not set in the config file.');
            return;
        }

        /**
         * Checking if STK Validation URL is set in the config file...
         */
        if (!config('mpesa.stk_validation_url')) {
            $this->error('STK Validation URL is not set in the config file.');
            return;
        }

        /**
         * Performing URL registration
         */
        $mpesa = new Mpesa();
        $result = json_decode($mpesa->c2bRegisterUrl());

        try {
            if ($result->ResponseCode == 0) {
                $this->info('C2B URLs registered successfully.');
            } else {
                $this->error('Failed to register C2B URLs.');
            }
        } catch (\Throwable $th) {
            $this->error($result->errorMessage);
        }
    }
}

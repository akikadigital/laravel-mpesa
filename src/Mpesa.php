<?php

namespace Akika\LaravelMpesa;

use Akika\LaravelMpesa\Models\Token;
use Akika\LaravelMpesa\Traits\MpesaTrait;

class Mpesa
{
    use MpesaTrait;

    public $url;

    public function __construct()
    {
        $this->url = config('mpesa.env') === 'sandbox' ? 'https://sandbox.safaricom.co.ke' : 'https://api.safaricom.co.ke';
    }

    public function hello()
    {
        return 'Hello from Mpesa';
    }

    public function fetchToken()
    {
        $token = Token::first();

        if (!$token || $token->timeToExpiry() <= 30) {
            $token = $this->generateToken();
            Token::create([
                "access_token" => $token->access_token,
                "requested_at" => now(),
                "expires_at" => now()->addSeconds($token->expires_in)
            ]);
        }

        return $token->access_token;
    }
}

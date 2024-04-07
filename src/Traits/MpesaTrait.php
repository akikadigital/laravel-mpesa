<?php

namespace Akika\LaravelMpesa\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

trait MpesaTrait
{

    function makeRequest($url, $body)
    {
        // Convert the above code to use Http
        $response = Http::withToken($this->fetchToken())
            ->acceptJson()
            ->post($url, $body);

        return $response;
    }

    function getIdentifierType($type)
    {
        $type = strtolower($type);
        switch ($type) {
            case "msisdn":
                $x = 1;
                break;
            case "tillnumber":
                $x = 2;
                break;
            case "shortcode":
                $x = 4;
                break;
        }
        return $x;
    }

    function generatePassword()
    {
        $timestamp = Carbon::now()->format('YmdHis');
        $shortcode = config('mpesa.shortcode');
        $passkey = config('mpesa.stk_passkey');
        $password = base64_encode($shortcode . $passkey . $timestamp);

        return $password;
    }

    function generateCertificate()
    {
        if (config('mpesa.env') == 'sandbox') {
            $publicKey = File::get(__DIR__ . '/../../certificates/SandboxCertificate.cer');
        } else {
            $publicKey = File::get(__DIR__ . '/../../certificates/ProductionCertificate.cer');
        }
        openssl_public_encrypt(config('mpesa.initiator_password'), $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);

        return base64_encode($encrypted);
    }

    function sanitizePhoneNumber($phoneNumber)
    {
        $phoneNumber = str_replace(" ", "", $phoneNumber); // remove spaces
        $phone_number = "254" . substr($phoneNumber, -9); // remove leading 0 and replace with 254
        return $phone_number;
    }

    function isValidUrl($url)
    {
        // check if $url is a valid url and has not include keywords like mpesa,safaricom etc
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            if (strpos($url, 'mpesa') !== false || strpos($url, 'safaricom') !== false) {
                return false;
            }
            return true;
        }
    }
}

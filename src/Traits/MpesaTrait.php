<?php

namespace Akika\LaravelMpesa\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

trait MpesaTrait
{
    function generateToken()
    {
        $consumer_key = config('mpesa.consumer_key');
        $consumer_secret = config('mpesa.consumer_secret');

        $url = $this->url . '/oauth/v1/generate?grant_type=client_credentials';

        $response = Http::withBasicAuth($consumer_key, $consumer_secret)
            ->get($url);

        return $response;
    }

    function makeRequest($url, $body)
    {
        // $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, $url);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $this->fetchToken()));
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($ch, CURLOPT_POST, true);
        // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // $response = curl_exec($ch);
        // curl_close($ch);
        // return $response;

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

    function isValidUrl($url) {
        // check if $url is a valid url and has not include keywords like mpesa,safaricom etc
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            if (strpos($url, 'mpesa') !== false || strpos($url, 'safaricom') !== false) {
                return false;
            }
            return true;
        }
    }
}

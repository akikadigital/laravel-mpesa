<?php

namespace Akika\LaravelMpesa\Traits;

trait MpesaTrait
{
    function generateToken()
    {
        $consumer_key = config('mpesa.consumer_key');
        $consumer_secret = config('mpesa.consumer_secret');

        $url = $this->url . '/oauth/v1/generate?grant_type=client_credentials';
        info("TOKEN_URL: " . $url);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        // generate base64 encoded string of consumer key and consumer secret
        $credentials = base64_encode($consumer_key . ':' . $consumer_secret);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $curl_response = curl_exec($curl);

        curl_close($curl);

        return json_decode($curl_response);
    }

    function performCurlRequest($url, $body)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization:Bearer ' . $this->fetchToken()));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}

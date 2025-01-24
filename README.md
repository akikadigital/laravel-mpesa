## Laravel Mpesa Package by [Akika Digital](https://akika.digital)

This Laravel package provides convenient methods for integrating [Mpesa Daraja API's](https://developer.safaricom.co.ke/APIs) functionalities into your Laravel application.

## Installation

You can install the package via composer:

```bash
composer require akika/laravel-mpesa
```

After installing the package, publish the configuration file using the following command:

```bash
php artisan mpesa:install
```

This will generate a mpesa.php file in your config directory where you can set your Mpesa credentials and other configuration options.

## .env file Setup

Add the following configurations into the .env file

```
MPESA_ENV=
MPESA_APP_DEBUG=
MPESA_SHORTCODE=
MPESA_CONSUMER_KEY=
MPESA_CONSUMER_SECRET=
MPESA_PASSKEY=
MPESA_INITIATOR_NAME=
MPESA_INITIATOR_PASSWORD=
MPESA_STK_VALIDATION_URL=
MPESA_STK_CONFIRMATION_URL=
MPESA_STK_CALLBACK_URL=
MPESA_BALANCE_RESULT_URL=
MPESA_BALANCE_TIMEOUT_URL=
MPESA_TRANSACTION_STATUS_RESULT_URL=
MPESA_TRANSACTION_STATUS_TIMEOUT_URL=
MPESA_B2C_TIMEOUT_URL=
MPESA_B2C_RESULT_URL=
MPESA_B2B_TIMEOUT_URL=
MPESA_B2B_RESULT_URL=
MPESA_REVERSAL_TIMEOUT_URL=
MPESA_REVERSAL_RESULT_URL=
MPESA_BILL_OPTIN_CALLBACK_URL=
MPESA_TAX_REMITTANCE_TIMEOUT_URL=
MPESA_TAX_REMITTANCE_RESULT_URL=
```

NOTE: The mpesa.php config file sets the default `MPESA_ENV` value to `sandbox`. This will always load sandbox urls.

## Function Responses

All responses, except the token generation response, conform to the responses documented on the daraja portal.

## Usage

### Initializing Mpesa

```php
use Akika\LaravelMpesa\Mpesa;

$mpesa = new Mpesa();
```

### Fetching Token

You can fetch the token required for Mpesa API calls as follows:

```php
$token = $mpesa->getToken();
```

### Getting Account Balance

You can fetch mpesa account balance as follows:

```php
$balance = $mpesa->getBalance();
```

### C2B Transactions

#### Registering URLs for C2B Transactions

You can register validation and confirmation URLs for C2B transactions:

```php
$response = $mpesa->c2bRegisterUrl();
```

You can register the C2B URLs using the provided command below:

```php
php artisan mpesa:register-c2b-urls
```

The above command requires you to have set the below variables in your env or in the config file:

```
MPESA_SHORTCODE=
MPESA_STK_VALIDATION_URL=
MPESA_STK_CONFIRMATION_URL=
```

#### Simulating C2B Transactions

You can simulate payment requests from clients:

```php
$response = $mpesa->c2bSimulate($amount, $phoneNumber, $billRefNumber, $commandID);
```

#### Initiating STK Push

You can initiate online payment on behalf of a customer:

```php
$response = $mpesa->stkPush($accountNumber, $phoneNumber, $amount, $transactionDesc);
```

#### Querying STK Push Status

You can query the result of a STK Push transaction:

```php
$response = $mpesa->stkPushStatus($checkoutRequestID);
```

#### Reversing Transactions

You can reverse a C2B M-Pesa transaction:

```php
$response = $mpesa->reverse($transactionId, $amount, $receiverShortCode, $remarks);
```

### Business to Customer (B2C) Transactions

You can perform Business to Customer transactions:

```php
$response = $mpesa->b2cTransaction($oversationId, $commandID, $msisdn, $amount, $remarks, $ocassion);
```

### Business to Business (B2B) Transactions

You can perform Business to Business transactions:

```php
$response = $mpesa->b2bPaybill($destShortcode, $amount, $remarks, $accountNumber, $requester);
```

### QR Code Generation

You can generate QR codes for making payments:

```php
$response = $mpesa->dynamicQR($merchantName, $refNo, $amount, $trxCode, $cpi, $size);
```

### Bill Manager

You can optin to the bill manager service and send invoices:

```php
$response = $mpesa->billManagerOptin($email, $phoneNumber);

$response = $mpesa->sendInvoice($reference, $billedTo, $phoneNumber, $billingPeriod, $invoiceName, $dueDate, $amount, $items);
```

### Tax Remittance

You can remit tax to the government:

```php
$response = $mpesa->taxRemittance($amount, $receiverShortCode, $accountReference, $remarks);
```

## License

The Laravel Mpesa package is open-sourced software licensed under the MIT license. See the LICENSE file for details.

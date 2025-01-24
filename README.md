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
MPESA_B2C_TOPUP_TIMEOUT_URL=
MPESA_B2C_TOPUP_RESULT_URL=
MPESA_B2B_TIMEOUT_URL=
MPESA_B2B_RESULT_URL=
MPESA_B2B_EXPRESS_CHECKOUT_URL=
MPESA_REVERSAL_TIMEOUT_URL=
MPESA_REVERSAL_RESULT_URL=
MPESA_BILL_MANAGER_LOGO_URL=
MPESA_BILL_MANAGER_OPTIN_CALLBACK_URL=
MPESA_TAX_REMITTANCE_TIMEOUT_URL=
MPESA_TAX_REMITTANCE_RESULT_URL=
MPESA_RATIBA_CALLBACK_URL=
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

- `$transactionDesc` can be null

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

- `$ocassion` is an optional field.

### Business to Customer (B2C) Transactions

You can perform Business to Customer transactions:

```php
$response = $mpesa->b2cTransaction($oversationId, $commandID, $msisdn, $amount, $remarks, $ocassion);
```

- `$ocassion` is an optional field.

### B2C Topup

This API enables you to load funds to a B2C shortcode directly for disbursement. The transaction moves money from your MMF/Working account to the recipient’s utility account.

```php
$response = $mpesa->b2cTopup($accountReference, $receiverShortCode, $amount, $remarks);
```

- $accountReference: A unique (system generated) identifier for the transaction.
- $receiverShortCode: The shortcode to which money will be moved
- $amount: The transaction amount.
- $remarks: Any additional information to be associated with the transaction.

### Business to Business (B2B) Transactions

#### B2B Paybill

You can perform Business to Business transactions:

```php
$response = $mpesa->b2bPaybill($destShortcode, $amount, $remarks, $accountNumber, $resultUrl, $timeoutUrl, $requester = null);
```

- `$destShortcode`: This is the party receiving the money.
- `$accountNumber`: The account number to be associated with the payment. Up to 13 characters.
- `$requester` is an optional field.

#### B2B Buy Goods

This api accepts variables as provided in section [B2B Paybill](b2b-paybill) above.

```php
$response = $mpesa->b2bBuyGoods($destShortcode, $amount, $remarks, $accountNumber, $resultUrl, $timeoutUrl, $requester = null);
```

#### B2B Express Checkout

```php
$response = $mpesa->b2bExpressCheckout($destShortcode, $partnerName, $amount, $paymentReference, $requestRefID);
```

- `$destShortcode`: This is the party receiving the money.
- `$partnerName`: This is the organization Friendly name used by the vendor as known by the Merchant.
- `$paymentReference`: This is a reference to the payment being made. This will appear in the text for easy reference by the merchant. e.g. Order ID
- `$requestRefID`: This is an auto-genarated reference ID generated by your system.

### QR Code Generation

You can generate QR codes for making payments:

```php
$response = $mpesa->dynamicQR($merchantName, $refNo, $trxCode, $cpi, $size, $amount = null);
```

- `$amount` is an optional field

### Bill Manager

You can optin to the bill manager service and send invoices:

```php
$response = $mpesa->billManagerOptin($email, $phoneNumber, $sendReminders);

$response = $mpesa->sendInvoice($reference, $billedTo, $phoneNumber, $billingPeriod, $invoiceName, $dueDate, $amount, $items);
```

- `$sendReminders` is a boolean field. Allows true or false (1 or 0)

### Tax Remittance

You can remit tax to the government:

```php
$response = $mpesa->taxRemittance($amount, $receiverShortCode, $accountReference, $remarks);
```

### Mpesa Ratiba

The Standing Order APIs enable teams to integrate with the standing order solution by initiating a request to create a standing order on the customer profile.

```php
$response = $mpesa->ratiba($name, $startDate, $endDate, $transactionType, $type, $amount, $msisdn,$accountReference, $transactionDesc, $frequency)
```

- $name: Name of standing order that must be unique for each customer.
- $startDate: The date you wish for the standing order to start executing
- $endDate: The date you wish for the standing order to stop executing
- $transactionType: This is the transaction type that is used to identify the transaction when sending the request to M-PESA. Either till or paybill
- $type: This is the transaction type that is used to identify the transaction when sending the request to M-PESA.
- $amount: This is the money that the customer pays to the Shortcode.
- $phoneNumber: The phone number sending money. The parameter expected is a Valid Safaricom Mobile Number that is M-PESA registered in the format 2547XXXXXXXX
- $accountReference: This is a unique identifier for the transaction and is generated by the system. It has a maximum limit of 13 characters.
- $transactionDesc: This is any additional information/comment that can be sent along with the request from your system. Maximum of 13 Characters
- $frequency: The frequency of the standing order (one-off, daily, weekly, monthly, bi-monthly, quarterly, half-year, annually)

### Mpesa Transaction History

The following API takes in the start and and end dates and returns the transactions between that period.

```php
$response = $mpesa->mpesaTransactionsHistory($startDate, $endDate, $offset = 0);
```

#### Successful Response

```php
{
    "ResponseRefID": "8bab-42cc-bb85-5056a6c01e6915928124",
    "ResponseCode": "1000",
    "ResponseMessage": "Success",
    "Response": [
        [
            {
                "transactionId": "XXXXXXXXX",
                "trxDate": "2025-01-24T10:56:11+03:00",
                "msisdn": 2547XXXXXXXX,
                "sender": "MPESA",
                "transactiontype": "c2b-buy-goods-debit",
                "billreference": "",
                "amount": "2697.0",
                "organizationname": "VENDOR"
            }
        ]
    ]
}
```

## API Response Body

$response has the following as a json object

```php
{
    "OriginatorConversationID": "5118-111210482-1",
    "ConversationID": "AG_20230420_2010759fd5662ef6d054",
    "ResponseCode": "0",
    "ResponseDescription": "Accept the service request successfully."
}
```

## Succssful result body

A successful result body has the following structure

```php
{
 "Result":
 {
   "ResultType": "0",
   "ResultCode":"0",
   "ResultDesc": "The service request is processed successfully",
   "OriginatorConversationID":"626f6ddf-ab37-4650-b882-b1de92ec9aa4",
   "ConversationID":"12345677dfdf89099B3",
   "TransactionID":"QKA81LK5CY",
   "ResultParameters":
     {
       "ResultParameter":
          [{
           "Key":"DebitAccountBalance",
           "Value":"{Amount={CurrencyCode=KES, MinimumAmount=618683, BasicAmount=6186.83}}"
          },
          {
          "Key":"Amount",
           "Value":"190.00"
          },
           {
          "Key":"DebitPartyAffectedAccountBalance",
           "Value":"Working Account|KES|346568.83|6186.83|340382.00|0.00"
          },
           {
          "Key":"TransCompletedTime",
           "Value":"20221110110717"
          },
           {
          "Key":"DebitPartyCharges",
           "Value":""
          },
           {
          "Key":"ReceiverPartyPublicName",
           "Value":000000– Biller Companty
          },
          {
          "Key":"Currency",
           "Value":"KES"
          },
          {
           "Key":"InitiatorAccountCurrentBalance",
           "Value":"{Amount={CurrencyCode=KES, MinimumAmount=618683, BasicAmount=6186.83}}"
          }]
       },
     "ReferenceData":
       {
        "ReferenceItem":[
           {"Key":"BillReferenceNumber", "Value":"19008"},
           {"Key":"QueueTimeoutURL", "Value":"https://mydomain.com/b2b/businessbuygoods/queue/"}
         ]
      }
 }
}
```

## Unsuccessful reusult body

An unsuccessful result body has the following structure

```php
{
 "Result":
 {
   "ResultType":0,
   "ResultCode":2001,
   "ResultDesc":"The initiator information is invalid.",
   "OriginatorConversationID":"12337-23509183-5",
   "ConversationID":"AG_20200120_0000657265d5fa9ae5c0",
   "TransactionID":"OAK0000000",
   "ResultParameters":{
     "ResultParameter":{
        "Key":"BillReferenceNumber",
        "Value":12323333
      }
   },
   "ReferenceData":{
     "ReferenceItem":[
      {
        "Key":"BillReferenceNumber",
        "Value":12323333
      },
      {
        "Key":"QueueTimeoutURL",
        "Value":"https://internalapi.safaricom.co.ke/mpesa/abresults/v1/submit"
      }
      {
        "Key":"Occassion"
      }
     ]
    }
 }
}
```

## License

The Laravel Mpesa package is open-sourced software licensed under the MIT license. See the LICENSE file for details.

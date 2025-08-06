<?php

namespace Akika\LaravelMpesa;

use Akika\LaravelMpesa\Traits\MpesaTrait;
use Carbon\Carbon;

class Mpesa
{
    use MpesaTrait;

    public $environment;
    public $url;

    public $initiatorName;
    public $mpesaShortCode;
    public $securityCredential;

    public $consumerKey;
    public $consumerSecret;

    public $debugMode;

    /**
     * Initialize the Mpesa class with the necessary credentials
     */

    public function __construct()
    {
        $this->environment = config('mpesa.env');
        $this->debugMode = config('mpesa.debug');
        $this->url = config('mpesa.url.' . $this->environment);

        $this->mpesaShortCode = config('mpesa.shortcode');
        $this->initiatorName = config('mpesa.initiator_name');
        $this->securityCredential = $this->generateCertificate();

        $this->consumerKey = config('mpesa.consumer_key');
        $this->consumerSecret = config('mpesa.consumer_secret');
    }

    public function getToken()
    {
        $result = $this->fetchToken();

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('Get Token: ' . $result);
        }

        // return the result
        return $result;
    }

    // --------------------------------- Account Balance ---------------------------------

    /**
     * Get the balance of the M-Pesa account on daraja
     * @result - The result of the request: \Illuminate\Http\Client\Response
     */

    public function getBalance()
    {
        $url = $this->url . '/mpesa/accountbalance/v1/query';
        $data = [
            'Initiator'             => $this->initiatorName, // This is the credential/username used to authenticate the transaction request
            'SecurityCredential'    => $this->securityCredential, // Base64 encoded string of the M-PESA short code and password, which is encrypted using M-PESA public key and validates the transaction on M-PESA Core system.
            'CommandID'             => 'AccountBalance', // A unique command is passed to the M-PESA system.
            'PartyA'                => $this->mpesaShortCode, // The shortcode of the organization querying for the account balance.
            'IdentifierType'        => $this->getIdentifierType("shortcode"), // Type of organization querying for the account balance.
            'Remarks'               => "balance", // String sequence of characters up to 100
            'QueueTimeOutURL'       => config('mpesa.balance_timeout_url'),
            'ResultURL'             => config('mpesa.balance_result_url')
        ];

        // check if $data['ResultURL'] is set and that it is a valid url
        if (!$this->isValidUrl($data['ResultURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('Balance Request Data: ' . json_encode($data));
            info('Balance Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    // --------------------------------- C2B Transactions ---------------------------------

    /**
     * Register the validation and confirmation urls for the C2B transactions
     * This is the first step in the C2B transaction process
     * The validation url is used to validate the transaction before it is processed
     * The confirmation url is used to confirm the transaction after it has been processed
     * For example, a bank would want to verify if an account number exists in their platform before accepting a payment from the customer.
     * @result - The result of the request: \Illuminate\Http\Client\Response
     */

    public function c2bRegisterUrl()
    {
        $url = $this->url . '/mpesa/c2b/v2/registerurl';

        $data = [
            'ShortCode'     => $this->mpesaShortCode,
            'ResponseType'   => 'Completed', // [Canceled | Completed] . Default is Completed.
            'ConfirmationURL' => config('mpesa.stk_confirmation_url'),
            'ValidationURL' => config('mpesa.stk_validation_url')
        ];

        // check if $data['ConfirmationURL] and $data['ValidationURL] are set and that they are valid urls
        if (!$this->isValidUrl($data['ConfirmationURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid ConfirmationURL');
        }

        if (!$this->isValidUrl($data['ValidationURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid ValidationURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('C2B Register URL Data: ' . json_encode($data));
            info('C2B Register URL Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /**
     * This API is used to simulate payment requests from clients and to your API.
     * It basically simulates a payment made from the client phone's STK/SIM Toolkit menu, and enables you to receive the payment requests in real time.
     * 
     * @param $amount - The amount to be paid
     * @param $phoneNumber - The phone number making the payment
     * @param $billRefNumber - The account number to be credited
     * @param $commandID - The type of transaction being performed. Can either be CustomerPayBillOnline or CustomerBuyGoodsOnline
     *   - CustomerPayBillOnline : When a customer goes to M-Pesa > Lipa na M-Pesa > Paybill and enters your paybill number.
     *   - CustomerBuyGoodsOnline : When a customer goes to M-Pesa > Lipa na M-Pesa > Buy Goods and Services and enters your till number.
     * @result - The result of the request: \Illuminate\Http\Client\Response
     */

    public function c2bSimulate($amount, $phoneNumber, $billRefNumber, $commandID = 'CustomerPayBillOnline')
    {
        $url = $this->url . '/mpesa/c2b/v1/simulate';
        $data = [
            'ShortCode'     => $this->mpesaShortCode,
            'CommandID'     => $commandID, // CustomerPayBillOnline | CustomerBuyGoodsOnline
            'Amount'        => floor($amount), // remove decimal points
            'Msisdn'        => $this->sanitizePhoneNumber($phoneNumber),
            'BillRefNumber' => $billRefNumber
        ];

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('C2B Simulate Data: ' . json_encode($data));
            info('C2B Simulate Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /**
     * This API is used to initiate online payment on behalf of a customer.
     * It is used to simulate the process of a customer paying for goods or services.
     * The transaction moves money from the customer’s account to the business account.
     * The customer will receive a propmt to enter their M-Pesa pin to complete the transaction.
     * @param $amount - The amount to be paid
     * @param $phoneNumber - The phone number making the payment
     * @param $transactionDesc - A description of the transaction
     * @param $accountNumber - The account number to be credited
     * 
     * @result - The result of the request: \Illuminate\Http\Client\Response
     */

    public function stkPush($accountNumber, $phoneNumber, $amount, $transactionDesc = null)
    {
        $url = $this->url . '/mpesa/stkpush/v1/processrequest';
        $data = [
            'BusinessShortCode'     => $this->mpesaShortCode,
            'Password'              => $this->generatePassword(), // base64.encode(Shortcode+Passkey+Timestamp)
            'Timestamp'             => Carbon::rawParse('now')->format('YmdHis'),
            'TransactionType'       => 'CustomerPayBillOnline',
            'Amount'                => floor($amount), // remove decimal points
            'PartyA'                => $this->sanitizePhoneNumber($phoneNumber),
            'PartyB'                => $this->mpesaShortCode,
            'PhoneNumber'           => $this->sanitizePhoneNumber($phoneNumber),
            'AccountReference'      => $accountNumber, //Account Number for a paybill..Maximum of 12 Characters.,
            'TransactionDesc'       => $transactionDesc ? substr($transactionDesc, 0, 13) : 'STK Push', // Should not exceed 13 characters
            'CallBackURL'           => config('mpesa.stk_callback_url'),
        ];

        // check if $data['CallBackURL] is set and that it is a valid url
        if (!$this->isValidUrl($data['CallBackURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid CallBackURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('STK Push Data: ' . json_encode($data));
            info('STK Push Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /**
     * This API is used to query the result of a STK Push transaction.
     * This is done by using the M-Pesa code and the phone number used in the transaction.
     * @param $checkoutRequestID - This is a global unique identifier of the processed checkout transaction request.
     *   - It is found in the result of the checkout request.
     * 
     * @result - The result of the request: \Illuminate\Http\Client\Response
     */

    public function stkPushStatus($checkoutRequestID)
    {
        $url = $this->url . '/mpesa/stkpushquery/v1/query';
        $data = [
            'BusinessShortCode'     => $this->mpesaShortCode,
            'Password'              => $this->generatePassword(),
            'Timestamp'             => Carbon::rawParse('now')->format('YmdHis'), // Date in format - YYYYMMDDHHmmss
            'CheckoutRequestID'     => $checkoutRequestID // This is a global unique identifier of the processed checkout transaction request.
        ];

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('STK Push Status Data: ' . json_encode($data));
            info('STK Push Status Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /** 
     * Reverses a C2B M-Pesa transaction.
     * Once a customer pays and there is a need to reverse the transaction, the organization will use this API to reverse the amount.
     * @param $transactionId - The transaction ID of the transaction to be reversed
     * @param $amount - The amount to be reversed
     * @param $receiverShortCode - The shortcode of the organization that receives the transaction
     * @param $remarks - Comments that are sent along with the transaction
     * 
     * @result - The result of the request: \Illuminate\Http\Client\Response
     */
    public function reverse($transactionId, $amount, $receiverShortCode, $remarks)
    {
        $url = $this->url . '/mpesa/reversal/v1/request';
        $data = [
            'Initiator'                 =>  $this->initiatorName, // The name of the initiator to initiate the request.
            'SecurityCredential'        =>  $this->securityCredential,
            'CommandID'                 =>  'TransactionReversal',
            "TransactionID"             =>  $transactionId, // Payment transaction ID of the transaction that is being reversed. e.g. LKXXXX1234
            "Amount"                    =>  $amount, // The transaction amount
            "ReceiverParty"             =>  $receiverShortCode, // The organization that receives the transaction.
            "RecieverIdentifierType"    =>  $this->getIdentifierType("shortcode"), // Type of organization that receives the transaction.
            "Remarks"                   =>  $remarks ?? "please", // Comments that are sent along with the transaction.
            "Occasion"                  =>  "", // Optional Parameter.
            "ResultURL"                 =>  config('mpesa.reversal_result_url'),
            "QueueTimeOutURL"           =>  config('mpesa.reversal_timeout_url'),
        ];

        // check if $data['ResultURL] is set and that it is a valid url
        if (!$this->isValidUrl($data['ResultURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }

        // check if $data['QueueTimeOutURL] is set and that it is a valid url
        if (!$this->isValidUrl($data['QueueTimeOutURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid QueueTimeOutURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('Reversal Data: ' . json_encode($data));
            info('Reversal Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    // --------------------------------- B2C Transactions ---------------------------------

    /**
     * This API enables Business to Customer (B2C) transactions between a company and customers who are the end-users of its products or services.
     * It is used to send money from a company to customers e.g. salaries, winnings, refunds, etc.
     * @param $coversationId - This is a unique string you specify for every API request you simulate
     * @param $commandID - This is a unique command that specifies B2C transaction type.
            - SalaryPayment: This supports sending money to both registered and unregistered M-Pesa customers.
            - BusinessPayment: This is a normal business to customer payment, supports only M-PESA registered customers.
            - PromotionPayment: This is a promotional payment to customers. The M-PESA notification message is a congratulatory message. Supports only M-PESA registered customers.
     * @param $msisdn - The phone number receiving the payment
     * @param $amount - The amount to be paid
     * @param $remarks - Comments that are sent along with the transaction
     * @param $ocassion - Optional. The reason for the transaction
     * 
     * @result - The result of the request: \Illuminate\Http\Client\Response
     */

    public function b2cTransaction($coversationId, $commandID, $msisdn, $amount, $remarks, $ocassion = null)
    {
        $url = $this->url . '/mpesa/b2c/v1/paymentrequest';
        $data = [
            'OriginatorConversationID' => $coversationId, // This is a unique string you specify for every API request you simulate
            'InitiatorName'         =>  $this->initiatorName, // This is an API user created by the Business Administrator of the M-PESA
            'SecurityCredential'    =>  $this->securityCredential, // This is the value obtained after encrypting the API initiator password.
            'CommandID'             =>  $commandID, // This is a unique command that specifies B2C transaction type.
            'Amount'                =>  floor($amount), // remove decimal points
            'PartyA'                =>  $this->mpesaShortCode,
            'PartyB'                =>  $this->sanitizePhoneNumber($msisdn),
            'Remarks'               =>  $remarks,
            'Occassion'              =>  $ocassion ? substr($ocassion, 0, 100) : '', // Can be null
            'QueueTimeOutURL'       =>  config('mpesa.b2c_timeout_url'),
            'ResultURL'             =>  config('mpesa.b2c_result_url'),
        ];

        // check if $data['ResultURL] is set and that it is a valid url
        if (!$this->isValidUrl($data['ResultURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }

        if (!$this->isValidUrl($data['QueueTimeOutURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid QueueTimeOutURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('B2C Transaction Data: ' . json_encode($data));
            info('B2C Transaction Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /**
     * This API enables you to load funds to a B2C shortcode directly for disbursement.
     * The transaction moves money from your MMF/Working account to the recipient’s utility account.
     * @param $accountReference - The account number to be credited
     * @param $receiverShortCode - The shortcode of the business receiving the payment
     * @param $amount - The amount to be paid
     * @param $resultUrl - The endpoint that receives the response of the transaction
     * @param $timeoutUrl - The endpoint that receives a timeout response
     * @param $remarks - Comments that are sent along with the transaction
     * 
     * @result - The result of the request: \Illuminate\Http\Client\Response
     */

    public function b2cTopup($accountReference, $receiverShortCode, $amount, $remarks)
    {
        $url = $this->url . '/mpesa/b2b/v1/paymentrequest';
        $data = [
            'Initiator'                 =>  $this->initiatorName,
            'SecurityCredential'        =>  $this->securityCredential,
            'CommandID'                 =>  'BusinessPayToBulk',
            'SenderIdentifierType'      =>  $this->getIdentifierType("shortcode"),
            'RecieverIdentifierType'    =>  $this->getIdentifierType("shortcode"),
            'Amount'                    =>  floor($amount),
            'PartyA'                    =>  $this->mpesaShortCode,
            'PartyB'                    =>  $receiverShortCode,
            'AccountReference'          =>  $accountReference,
            'Requester'                 =>  $this->sanitizePhoneNumber('254708374149'),
            'Remarks'                   =>  $remarks,
            'QueueTimeOutURL'           =>  config('mpesa.b2c_topup_timeout_url'),
            'ResultURL'                 =>  config('mpesa.b2c_topup_result_url')
        ];

        // check if $data['ResultURL] is set and that it is a valid url
        if (!$this->isValidUrl($data['ResultURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }

        if (!$this->isValidUrl($data['QueueTimeOutURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid QueueTimeOutURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('B2C Topup Data: ' . json_encode($data));
            info('B2C Topup Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /**
     * This API enables Business to Customer (B2C) transactions between a company and customers who are the end-users of its products or services.
     * Unlike b2cTransaction, this method validates the transaction before processing it.
     * @param $commandID - This is a unique command that specifies B2C transaction type.
     * @param $msisdn - The phone number receiving the payment
     * @param $amount - The amount to be paid
     * @param $remarks - Comments that are sent along with the transaction
     * @param $idNumber - The national ID number of the customer
     * @param $ocassion - Optional. The reason for the transaction
     * 
     * @result - The result of the request: \Illuminate\Http\Client\Response
     */

    public function validatedB2CTransaction($commandID, $msisdn, $amount, $remarks, $idNumber, $ocassion = null)
    {
        $url = $this->url . '/mpesa/b2c/v1/paymentrequest';
        $data = [
            'InitiatorName'         =>  $this->initiatorName,
            'SecurityCredential'    =>  $this->securityCredential,
            'CommandID'             =>  $commandID,
            'Amount'                =>  floor($amount), // remove decimal points
            'PartyA'                =>  $this->mpesaShortCode,
            'PartyB'                =>  $this->sanitizePhoneNumber($msisdn),
            'Remarks'               =>  $remarks,
            'Occasion'              =>  $ocassion, // Can be null
            'OriginatorConversationID' => Carbon::rawParse('now')->format('YmdHis'), //unique id for the transaction
            'IDType' => '01', //01 for national id
            'IDNumber' => $idNumber,
            'QueueTimeOutURL'       =>  config('mpesa.b2c_timeout_url'),
            'ResultURL'             =>  config('mpesa.b2c_result_url'),
        ];

        // check if $data['ResultURL] is set and that it is a valid url
        if (!$this->isValidUrl($data['ResultURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }

        if (!$this->isValidUrl($data['QueueTimeOutURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid QueueTimeOutURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('Validated B2C Transaction Data: ' . json_encode($data));
            info('Validated B2C Transaction Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    // --------------------------------- B2B Transactions ---------------------------------

    /**
     * This API enables you to pay bills directly from your business account to a pay bill number, or a paybill store. You can use this API to pay on behalf of a consumer/requester.
     * The transaction moves money from your MMF/Working account to the recipient’s utility account.
     * @param $destShortcode - The shortcode of the business receiving the payment
     * @param $amount - The amount to be paid
     * @param $remarks - Comments that are sent along with the transaction
     * @param $accountNumber - The account number to be associated with the payment
     * @param $requester - Optional. The consumer’s mobile number on behalf of whom you are paying.
     * 
     * @result - The result of the request: \Illuminate\Http\Client\Response
     */

    public function b2bPaybill($destShortcode, $amount, $remarks, $accountNumber, $requester = null)
    {
        //DisburseFundsToBusiness
        $url = $this->url . '/mpesa/b2b/v1/paymentrequest';
        $data = [
            'Initiator'                 =>  $this->initiatorName,
            'SecurityCredential'        =>  $this->securityCredential,
            'CommandID'                 =>  'BusinessPayBill', // This specifies the type of transaction being performed. There are five allowed values on the API: BusinessPayBill, BusinessBuyGoods, DisburseFundsToBusiness, BusinessToBusinessTransfer or MerchantToMerchantTransfer.
            'SenderIdentifierType'      =>  $this->getIdentifierType("shortcode"),
            'RecieverIdentifierType'    =>  $this->getIdentifierType("shortcode"),
            'Amount'                    =>  floor($amount), // remove decimal points
            'PartyA'                    =>  $this->mpesaShortCode,
            'PartyB'                    =>  $destShortcode,
            'AccountReference'          =>  $accountNumber, // The account number to be associated with the payment. Up to 13 characters.
            'Requester'                 =>  $this->sanitizePhoneNumber($requester), // Optional. The consumer’s mobile number on behalf of whom you are paying.
            'Remarks'                   =>  $remarks,
            'QueueTimeOutURL'           =>  config('mpesa.b2b_timeout_url'),
            'ResultURL'                 =>  config('mpesa.b2b_result_url')
        ];

        // check if $data['ResultURL] is set and that it is a valid url
        if (!$this->isValidUrl($data['ResultURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }

        // check if $data['QueueTimeOutURL] is set and that it is a valid url
        if (!$this->isValidUrl($data['QueueTimeOutURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid QueueTimeOutURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('B2B Paybill Data: ' . json_encode($data));
            info('B2B Paybill Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /**
     * This API enables you to pay for goods and services directly from your business account to a till number, merchant store number or Merchant HO. You can also use this API to pay a merchant on behalf of a consumer/requestor. 
     * The transaction moves money from your MMF/Working account to the recipient’s merchant account.
     * @param $destShortcode - The shortcode of the business receiving the payment
     * @param $amount - The amount to be paid
     * @param $remarks - Comments that are sent along with the transaction
     * @param $accountNumber - The account number to be associated with the payment
     * @param $requester - Optional. The consumer’s mobile number on behalf of whom you are paying.
     * 
     * @result - The result of the request: \Illuminate\Http\Client\Response
     */

    public function b2bBuyGoods($destShortcode, $amount, $remarks, $accountNumber, $requester = null)
    {
        //DisburseFundsToBusiness
        $url = $this->url . '/mpesa/b2b/v1/paymentrequest';
        $data = [
            'Initiator'                 =>  $this->initiatorName,
            'SecurityCredential'        =>  $this->securityCredential,
            'CommandID'                 =>  'BusinessBuyGoods', // This specifies the type of transaction being performed. There are five allowed values on the API: BusinessPayBill, BusinessBuyGoods, DisburseFundsToBusiness, BusinessToBusinessTransfer or MerchantToMerchantTransfer.
            'SenderIdentifierType'      =>  $this->getIdentifierType("shortcode"),
            'RecieverIdentifierType'    =>  $this->getIdentifierType("tillnumber"),
            'Amount'                    =>  floor($amount), // remove decimal points
            'PartyA'                    =>  $this->mpesaShortCode,
            'PartyB'                    =>  $destShortcode,
            'AccountReference'          =>  $accountNumber, // The account number to be associated with the payment. Up to 13 characters.
            'Requester'                 =>  $this->sanitizePhoneNumber($requester), // Optional. The consumer’s mobile number on behalf of whom you are paying.
            'Remarks'                   =>  $remarks,
            'QueueTimeOutURL'           =>  config('mpesa.b2b_timeout_url'),
            'ResultURL'                 =>  config('mpesa.b2b_result_url')
        ];

        // check if $data['ResultURL] is set and that it is a valid url
        if (!$this->isValidUrl($data['ResultURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }

        // check if $data['QueueTimeOutURL] is set and that it is a valid url
        if (!$this->isValidUrl($data['QueueTimeOutURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid QueueTimeOutURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('B2B Buy Goods Data: ' . json_encode($data));
            info('B2B Buy Goods Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /**
     * This API enables you to transfer money from one company to another company within the same organization.
     * The transaction moves money from the organization’s working account to another organization’s utility account.
     * @param $destShortcode - The shortcode of the organization that receives the transaction
     * @param $partnerName - The name of the organization that receives the transaction
     * @param $amount - The amount to be paid
     * @param $paymentReference - The reference for the payment
     * @param $callbackUrl - The endpoint that receives the response of the transaction
     * @param $requestRefID - The unique reference for the transaction
     * 
     * @result - The result of the request: \Illuminate\Http\Client\Response
     */

    public function b2bExpressCheckout($destShortcode, $partnerName, $amount, $paymentReference, $requestRefID)
    {
        $url = $this->url . '/v1/ussdpush/get-msisdn';
        $data = [
            'primaryShortCode' => $this->mpesaShortCode,
            'receiverShortCode' => $destShortcode,
            'partnerName' => $partnerName,
            'amount' => floor($amount),
            'paymentRef' => $paymentReference,
            'callbackUrl' => config('mpesa.b2b_express_checkout_url'),
            'RequestRefID' => $requestRefID
        ];

        // check if $data['ResultURL] is set and that it is a valid url
        if (!$this->isValidUrl($data['callbackUrl'])) {
            // throw an exception instead
            throw new \Exception('Invalid callback Url');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('B2B Express Checkout Data: ' . json_encode($data));
            info('B2B Express Checkout Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /**
     * This API is used to query the status of a B2B transaction.
     * This is done by using the M-Pesa code and the phone number used in the transaction.
     * @param $transactionId - This is a unique identifier of the transaction returned in the response of the original transaction.
     * @param $remarks - Comments that are sent along with the transaction
     * @param $originalConversationId - This is a unique identifier of the transaction returned in the response of the original transaction.
     * @param $receiverShortCode - The shortcode of the organization that receives the transaction
     * @param $remarks - Comments that are sent along with the transaction
     * @param $originalConversationId - This is a unique identifier of the transaction returned in the response of the original transaction.
     * 
     * @result - The result of the request: \Illuminate\Http\Client\Response
     */

    public function getTransactionStatus($transactionId, $identifierType, $remarks, $originalConversationId)
    {
        $url = $this->url . '/mpesa/transactionstatus/v1/query';
        $data = [
            'Initiator'                 =>  $this->initiatorName,
            'SecurityCredential'        =>  $this->securityCredential,
            'CommandID'                 =>  'TransactionStatusQuery',
            'TransactionID'             =>  $transactionId, //Organization Receiving the funds. e.g. LXXXXXX1234
            'PartyA'                    =>  $this->mpesaShortCode,
            'IdentifierType'            =>  $this->getIdentifierType($identifierType),
            'Remarks'                   =>  $remarks,
            'Occasion'                  =>  NULL,
            'OriginalConversationID'    =>  $originalConversationId,
            'ResultURL'                 =>  config('mpesa.transaction_status_result_url'),
            'QueueTimeOutURL'           =>  config('mpesa.transaction_status_timeout_url'),
        ];

        // check if $data['ResultURL] is set and that it is a valid url
        if (!$this->isValidUrl($data['ResultURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }

        // check if $data['QueueTimeOutURL] is set and that it is a valid url
        if (!$this->isValidUrl($data['QueueTimeOutURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid QueueTimeOutURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('Transaction Status Data: ' . json_encode($data));
            info('Transaction Status Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    // --------------------------------- QR Code ---------------------------------

    /**
     * This  API is used to generate a QR code that can be used to make payments.
     * The QR code can be scanned by the customer to make payments.
     * @param $merchantName - Name of the Company/M-Pesa Merchant Name
     * @param $refNo - Transaction Reference
     * @param $amount - The total amount for the sale/transaction.
     * @param $trxCode - Transaction Type. The supported types are:
     *    - BG: Pay Merchant (Buy Goods).
     *    - WA: Withdraw Cash at Agent Till.
     *    - PB: Paybill or Business number.
     *    - SM: Send Money(Mobile number)
     *    - SB: Sent to Business. Business number CPI in MSISDN format.
     * @param $cpi - Credit Party Identifier. Can be a Mobile Number, Business Number, Agent Till, Paybill or Business number, or Merchant Buy Goods.
     * @param $size - Size of the QR code image in pixels. QR code image will always be a square image.
     * 
     * @result - The result of the request: \Illuminate\Http\Client\Response
     * 
     */

    public function dynamicQR($merchantName, $refNo, $trxCode, $cpi, $size, $amount = null)
    {
        $url = $this->url . '/mpesa/qrcode/v1/generate';
        $data = [
            'MerchantName' => $merchantName, // Name of the Company/M-Pesa Merchant Name
            'RefNo' => $refNo, // Transaction Reference
            'Amount' => floor($amount), // The total amount for the sale/transaction.
            'TrxCode' => $trxCode, // Transaction Type
            'CPI' => $cpi, // Credit Party Identifier. Can be a Mobile Number, Business Number, Agent Till, Paybill or Business number, or Merchant Buy Goods.
            'Size' => $size // Size of the QR code image in pixels. QR code image will always be a square image.
        ];

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('Dynamic QR Data: ' . json_encode($data));
            info('Dynamic QR Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    // --------------------------------- Bill Manager ---------------------------------

    /**
     * The first step in the bill manager process is to optin to the service.
     * This API is used to optin to the bill manager service.
     * @param $email - The email address of the business
     * @param $phoneNumber - The phone number of the business
     * @param $sendReminders - This field gives you the flexibility as a business to enable or disable sms payment reminders for invoices sent. [0 | 1]
     * 
     * @result - The result of the request: \Illuminate\Http\Client\Response
     */

    public function billManagerOptin($email, $phoneNumber, $sendReminders)
    {
        $url = $this->url . "/v1/billmanager-invoice/optin";
        $data = [
            'ShortCode' => $this->mpesaShortCode,
            'email' => $email,
            'officialContact' => $this->sanitizePhoneNumber($phoneNumber),
            'sendReminders' => $sendReminders ? 1 : 0, // [0 | 1] This field gives you the flexibility as a business to enable or disable sms payment reminders for invoices sent.
            'logo' => config('mpesa.logo_url'), // Optional : Image to be embedded in the invoices and receipts sent to your customer.
            'callbackurl' => config('mpesa.bill_optin_callback_url')
        ];

        // check if $data['callbackurl] is set and that it is a valid url
        if (!$this->isValidUrl($data['callbackurl'])) {
            // throw an exception instead
            throw new \Exception('Invalid callbackurl');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('Bill Manager Optin Data: ' . json_encode($data));
            info('Bill Manager Optin Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /**
     * This API is used to send an invoice to a customer.
     * The invoice can be for goods or services.
     * @param $reference - This is a unique invoice name on your system’s end. e.g. INV12345
     * @param $billedTo - Full name of the person being billed e.g. John Doe
     * @param $phoneNumber - Phone number of the person being billed e.g. 0712345678
     * @param $billingPeriod - The period for which the invoice is being sent e.g. 1st Jan 2021 - 31st Jan 2021
     * @param $invoiceName - A descriptive invoice name for what your customer is being billed. e.g. water bill
     * @param $dueDate - This is the date you expect the customer to have paid the invoice amount.
     * @param $amount - Total Invoice amount to be paid in Kenyan Shillings without special characters
     * @param $items - These are additional billable items that you need included in your invoice. The invoice can have multiple items in the below format
     *    $items[
     *        'itemName' => 'Food',
     *        'amount' => 100, // Optional
     *    ]
     * 
     * @result - The result of the request: \Illuminate\Http\Client\Response
     * 
     */

    public function sendInvoice($reference, $billedTo, $phoneNumber, $billingPeriod, $invoiceName, $dueDate, $amount, $items)
    {
        $url = $this->url . "/v1/billmanager-invoice/single-invoicing";
        $data = [
            'externalReference' => $reference, // This is a unique invoice name on your system’s end. e.g. INV12345
            'billedFullName' => $billedTo, // Full name of the person being billed e.g. John Doe
            'billedPhoneNumber' => $phoneNumber, // Phone number of the person being billed e.g. 0712345678
            'billedPeriod' => $billingPeriod,
            'invoiceName' => $invoiceName, // A descriptive invoice name for what your customer is being billed. e.g. water bill
            'dueDate' => date('Y-m-d', strtotime($dueDate)), // This is the date you expect the customer to have paid the invoice amount.
            'amount' => floor($amount), // Total Invoice amount to be paid in Kenyan Shillings without special characters
            'invoiceItems' => $items // These are additional billable items that you need included in your invoice. 
        ];

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('Send Invoice Data: ' . json_encode($data));
            info('Send Invoice Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    // --------------------------------- Tax Remittance ---------------------------------

    /**
     * This API is used to tax remmitance to the government.
     * The transaction moves money from the company’s account to the government account.
     * @param $amount - The amount to be paid
     * @param $receiverShortCode - The shortcode of the government receiving the payment
     * @param $accountReference - The account number to be credited
     * @param $remarks - Comments that are sent along with the transaction
     * 
     * @result - The result of the request: \Illuminate\Http\Client\Response
     * 
     */

    public function taxRemittance($amount, $receiverShortCode, $accountReference, $remarks)
    {
        $url = $this->url . '/mpesa/b2b/v1/remittax';
        $data = [
            'Initiator' => $this->initiatorName,
            'SecurityCredential' => $this->securityCredential,
            'CommandID' => 'BusinessPayment',
            'SenderIdentifierType' => $this->getIdentifierType("shortcode"),
            'RecieverIdentifierType' => $this->getIdentifierType("shortcode"),
            'Amount' => floor($amount),
            'PartyA' => $this->mpesaShortCode,
            'PartyB' => $receiverShortCode,
            'AccountReference' => $accountReference,
            'Remarks' => $remarks ?? 'Tax remittance',
            'QueueTimeOutURL' => config('mpesa.tax_remittance_timeout_url'),
            'ResultURL' => config('mpesa.tax_remittance_result_url')
        ];

        // check if $data['ResultURL] is set and that it is a valid url
        if (!$this->isValidUrl($data['ResultURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }

        // check if $data['QueueTimeOutURL] is set and that it is a valid url
        if (!$this->isValidUrl($data['QueueTimeOutURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid QueueTimeOutURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('Tax Remittance Data: ' . json_encode($data));
            info('Tax Remittance Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    // --------------------------------- Standing Orders ---------------------------------
    /**
     * This API is used to create a standing order.
     * A standing order is an instruction to a bank to pay a fixed amount at regular intervals from one account to another.
     * @param $name - The name of the standing order
     * @param $startDate - The date the standing order should start
     * @param $endDate - The date the standing order should end
     * @param $transactionType - The type of transaction (paybill or tillnumber)
     * @param $amount - The amount to be paid
     * @param $phoneNumber - The phone number making the payment
     * @param $callbackUrl - The url to receive the response
     * @param $accountReference - The account number to be credited
     * @param $transactionDesc - This is any additional information/comment that can be sent along with the request from your system. Maximum of 13 Characters
     * @param $frequency - The frequency of the standing order (one-off, daily, weekly, monthly, bi-monthly, quarterly, half-year, annually)
     * 
     * @result - The result of the request: \Illuminate\Http\Client\Response
     */

    public function ratiba($name, $startDate, $endDate, $transactionType, $amount, $phoneNumber, $accountReference, $transactionDesc, $frequency)
    {
        $url = $this->url . '/standingorder/v1/createStandingOrderExternal';
        $data = [
            'StandingOrderName' => $name,
            'StartDate' => date('Ymd', strtotime($startDate)),
            'EndDate' => date('Ymd', strtotime($endDate)),
            'BusinessShortCode' => $this->mpesaShortCode,
            'TransactionType' => $this->ratibaTransactionType($transactionType),
            'ReceiverPartyIdentifierType' => $this->getIdentifierType($transactionType),
            'Amount' => floor($amount),
            'PartyA' => $this->sanitizePhoneNumber($phoneNumber),
            'CallBackURL' => config('mpesa.ratiba_callback_url'),
            'AccountReference' => $accountReference,
            'TransactionDesc' => $transactionDesc,
            'Frequency' => $this->ratibaFrequency($frequency),
        ];

        // check if $data['CallBackURL] is set and that it is a valid url
        if (!$this->isValidUrl($data['CallBackURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid callback Url');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('Mpesa Ratiba Data: ' . json_encode($data));
            info('Mpesa Ratiba Data: ' . $result);
        }

        // return the result
        return $result;
    }

    // --------------------------------- Mpesa Transactions History ---------------------------------

    /**
     * This API is used to query the transactions history of a paybill or till number over a period of time.
     * @param $startDate - The start date of the transactions
     * @param $endDate - The end date of the transactions
     * @param $offset - The offset of the transactions
     * 
     * @result - The result of the request: \Illuminate\Http\Client\Response
     */

    public function mpesaTransactionsHistory($startDate, $endDate, $offset = 0)
    {
        $url = $this->url . '/pulltransactions/v1/query';
        $data = [
            'ShortCode' => $this->mpesaShortCode,
            'StartDate' => date('Y-m-d H:i:s', strtotime($startDate)),
            'EndDate' => date('Y-m-d H:i:s', strtotime($endDate)),
            'OffSetValue' => $offset
        ];

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if ($this->debugMode) {
            info('Mpesa Transactions History Data: ' . json_encode($data));
            info('Mpesa Transactions History Response Data: ' . $result);
        }

        // return the result
        return $result;
    }
}

<?php

namespace Akika\LaravelMpesa;

use Akika\LaravelMpesa\Models\Token;
use Akika\LaravelMpesa\Traits\MpesaTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class Mpesa
{
    use MpesaTrait;

    public $url;

    public $initiatorName;
    public $mpesaShortcode;
    public $securityCredential;

    public $consumerKey;
    public $consumerSecret;

    /**
     *   Initialize the Mpesa class with the necessary credentials
     */

    public function __construct()
    {
        $this->mpesaShortcode = config('mpesa.shortcode');
        $this->initiatorName = config('mpesa.initiator_name');
        $this->securityCredential = $this->generateCertificate();

        $this->consumerKey = config('mpesa.consumer_key');
        $this->consumerSecret = config('mpesa.consumer_secret');

        $this->url = config('mpesa.env') === 'sandbox' ? 'https://sandbox.safaricom.co.ke' : 'https://api.safaricom.co.ke';
    }

    // --------------------------------- Account Balance ---------------------------------

    /**
     *   Get the balance of the M-Pesa account on daraja
     */

    public function getBalance()
    {
        $url = $this->url . '/mpesa/accountbalance/v1/query';
        $data = [
            'Initiator'             => $this->initiatorName, // This is the credential/username used to authenticate the transaction request
            'SecurityCredential'    => $this->securityCredential, // Base64 encoded string of the M-PESA short code and password, which is encrypted using M-PESA public key and validates the transaction on M-PESA Core system.
            'CommandID'             => 'AccountBalance', // A unique command is passed to the M-PESA system.
            'PartyA'                => $this->mpesaShortcode, // The shortcode of the organization querying for the account balance.
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
        if (config('mpesa.debug')) {
            info('Balance Request Data: ' . json_encode($data));
            info('Balance Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    // --------------------------------- C2B Transactions ---------------------------------

    /**
     *   Register the validation and confirmation urls for the C2B transactions
     *   This is the first step in the C2B transaction process
     *   The validation url is used to validate the transaction before it is processed
     *   The confirmation url is used to confirm the transaction after it has been processed
     *   For example, a bank would want to verify if an account number exists in their platform before accepting a payment from the customer.
     */

    public function c2bRegisterUrl()
    {
        $url = $this->url . '/mpesa/c2b/v2/registerurl';
        $data = [
            'ShortCode'     => $this->mpesaShortcode,
            'ResponseType'   => 'Completed', // [Canceled | Completed] this is the default action value that determines what M-PESA will do in the scenario that your endpoint is unreachable or is unable to respond on time.
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
        if (config('mpesa.debug')) {
            info('C2B Register URL Data: ' . json_encode($data));
            info('C2B Register URL Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /**
     *   This API is used to simulate payment requests from clients and to your API.
     *   It basically simulates a payment made from the client phone's STK/SIM Toolkit menu, and enables you to receive the payment requests in real time.
     * 
     *  @param $amount - The amount to be paid
     *  @param $phoneNumber - The phone number making the payment
     *  @param $billRefNumber - The account number to be credited
     *  @param $commandID - The type of transaction being performed. Can either be CustomerPayBillOnline or CustomerBuyGoodsOnline
     *      - CustomerPayBillOnline : When a customer goes to M-Pesa > Lipa na M-Pesa > Paybill and enters your paybill number.
     *      - CustomerBuyGoodsOnline : When a customer goes to M-Pesa > Lipa na M-Pesa > Buy Goods and Services and enters your till number.
     */

    public function c2bSimulate($amount, $phoneNumber, $billRefNumber, $commandID = 'CustomerPayBillOnline')
    {
        $url = $this->url . '/mpesa/c2b/v1/simulate';
        $data = [
            'ShortCode'     => $this->mpesaShortcode,
            'CommandID'     => $commandID, // CustomerPayBillOnline | CustomerBuyGoodsOnline
            'Amount'        => floor($amount), // remove decimal points
            'Msisdn'        => $this->sanitizePhoneNumber($phoneNumber),
            'BillRefNumber' => $billRefNumber
        ];

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if (config('mpesa.debug')) {
            info('C2B Simulate Data: ' . json_encode($data));
            info('C2B Simulate Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /**
     *   This API is used to initiate online payment on behalf of a customer.
     *   It is used to simulate the process of a customer paying for goods or services.
     *   The transaction moves money from the customer’s account to the business account.
     *   The customer will receive a propmt to enter their M-Pesa pin to complete the transaction.
     *  @param $accountNumber - The account number to be credited
     *  @param $phoneNumber - The phone number making the payment
     *  @param $amount - The amount to be paid
     *  @param $transactionDesc - A description of the transaction
     */

    public function stkPush($accountNumber, $phoneNumber, $amount, $transactionDesc = null)
    {
        $url = $this->url . '/mpesa/stkpush/v1/processrequest';
        $data = [
            'BusinessShortCode'     => $this->mpesaShortcode,
            'Password'              => $this->generatePassword(), // base64.encode(Shortcode+Passkey+Timestamp)
            'Timestamp'             => Carbon::rawParse('now')->format('YmdHis'),
            'TransactionType'       => 'CustomerPayBillOnline',
            'Amount'                => floor($amount), // remove decimal points
            'PartyA'                => $this->sanitizePhoneNumber($phoneNumber),
            'PartyB'                => $this->mpesaShortcode,
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
        if (config('mpesa.debug')) {
            info('STK Push Data: ' . json_encode($data));
            info('STK Push Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /**
     *  This API is used to query the result of a STK Push transaction.
     *  This is done by using the M-Pesa code and the phone number used in the transaction.
     *  @param $checkoutRequestID - This is a global unique identifier of the processed checkout transaction request.
     *      - It is found in the result of the checkout request.
     */

    public function stkPushStatus($checkoutRequestID)
    {
        $url = $this->url . '/mpesa/stkpushquery/v1/query';
        $data = [
            'BusinessShortCode'     => $this->mpesaShortcode,
            'Password'              => $this->generatePassword(),
            'Timestamp'             => Carbon::rawParse('now')->format('YmdHis'), // Date in format - YYYYMMDDHHmmss
            'CheckoutRequestID'     => $checkoutRequestID // This is a global unique identifier of the processed checkout transaction request.
        ];

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if (config('mpesa.debug')) {
            info('STK Push Status Data: ' . json_encode($data));
            info('STK Push Status Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /** 
     *   Reverses a C2B M-Pesa transaction.
     *   Once a customer pays and there is a need to reverse the transaction, the organization will use this API to reverse the amount.
     *   @param $transactionId - The transaction ID of the transaction to be reversed
     *   @param $amount - The amount to be reversed
     *   @param $receiverShortCode - The shortcode of the organization that receives the transaction
     *   @param $remarks - Comments that are sent along with the transaction
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
        if(!$this->isValidUrl($data['QueueTimeOutURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid QueueTimeOutURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if (config('mpesa.debug')) {
            info('Reversal Data: ' . json_encode($data));
            info('Reversal Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    // --------------------------------- B2C Transactions ---------------------------------

    /**
     *   This API enables Business to Customer (B2C) transactions between a company and customers who are the end-users of its products or services.
     *   It is used to send money from a company to customers e.g. salaries, winnings, refunds, etc.
     *   @param $oversationId - This is a unique string you specify for every API request you simulate
     *   @param $commandID - This is a unique command that specifies B2C transaction type.
            - SalaryPayment: This supports sending money to both registered and unregistered M-Pesa customers.
            - BusinessPayment: This is a normal business to customer payment, supports only M-PESA registered customers.
            - PromotionPayment: This is a promotional payment to customers. The M-PESA notification message is a congratulatory message. Supports only M-PESA registered customers.
     *   @param $msisdn - The phone number receiving the payment
     *   @param $amount - The amount to be paid
     *   @param $remarks - Comments that are sent along with the transaction
     *   @param $ocassion - Optional. The reason for the transaction
     */

    public function b2cTransaction($oversationId, $commandID, $msisdn, $amount, $remarks, $ocassion = null)
    {
        $url = $this->url . '/mpesa/b2c/v1/paymentrequest';
        $data = [
            'OriginatorConversationID' => $oversationId, // This is a unique string you specify for every API request you simulate
            'InitiatorName'         =>  $this->initiatorName, // This is an API user created by the Business Administrator of the M-PESA
            'SecurityCredential'    =>  $this->securityCredential, // This is the value obtained after encrypting the API initiator password.
            'CommandID'             =>  $commandID, // This is a unique command that specifies B2C transaction type.
            'Amount'                =>  floor($amount), // remove decimal points
            'PartyA'                =>  $this->mpesaShortcode,
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

        if(!$this->isValidUrl($data['QueueTimeOutURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid QueueTimeOutURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if (config('mpesa.debug')) {
            info('B2C Transaction Data: ' . json_encode($data));
            info('B2C Transaction Response Data: ' . $result);
        }

         // return the result
        return $result;
    }

    /**
     *   This API enables Business to Customer (B2C) transactions between a company and customers who are the end-users of its products or services.
     *   Unlike b2cTransaction, this method validates the transaction before processing it.
     *   @param $commandID - This is a unique command that specifies B2C transaction type.
     *   @param $msisdn - The phone number receiving the payment
     *   @param $amount - The amount to be paid
     *   @param $remarks - Comments that are sent along with the transaction
     *   @param $idNumber - The national ID number of the customer
     *   @param $ocassion - Optional. The reason for the transaction
     */

    public function validatedB2CTransaction($commandID, $msisdn, $amount, $remarks, $idNumber, $ocassion = null)
    {
        $url = $this->url . '/mpesa/b2c/v1/paymentrequest';
        $data = [
            'InitiatorName'         =>  $this->initiatorName,
            'SecurityCredential'    =>  $this->securityCredential,
            'CommandID'             =>  $commandID,
            'Amount'                =>  floor($amount), // remove decimal points
            'PartyA'                =>  $this->mpesaShortcode,
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

        if(!$this->isValidUrl($data['QueueTimeOutURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid QueueTimeOutURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if (config('mpesa.debug')) {
            info('Validated B2C Transaction Data: ' . json_encode($data));
            info('Validated B2C Transaction Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    // --------------------------------- B2B Transactions ---------------------------------

    /**
     *   This API enables you to pay bills directly from your business account to a pay bill number, or a paybill store. You can use this API to pay on behalf of a consumer/requester.
     *   The transaction moves money from your MMF/Working account to the recipient’s utility account.
     *  @param $destShortcode - The shortcode of the business receiving the payment
     *  @param $amount - The amount to be paid
     *  @param $remarks - Comments that are sent along with the transaction
     *  @param $accountNumber - The account number to be associated with the payment
     *  @param $requester - Optional. The consumer’s mobile number on behalf of whom you are paying.
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
            'PartyA'                    =>  $this->mpesaShortcode,
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
        if(!$this->isValidUrl($data['QueueTimeOutURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid QueueTimeOutURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if (config('mpesa.debug')) {
            info('B2B Paybill Data: ' . json_encode($data));
            info('B2B Paybill Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /**
     *   This API enables you to pay for goods and services directly from your business account to a till number, merchant store number or Merchant HO. You can also use this API to pay a merchant on behalf of a consumer/requestor. 
     *   The transaction moves money from your MMF/Working account to the recipient’s merchant account.
     *  @param $destShortcode - The shortcode of the business receiving the payment
     *  @param $amount - The amount to be paid
     *  @param $remarks - Comments that are sent along with the transaction
     *  @param $accountNumber - The account number to be associated with the payment
     *  @param $requester - Optional. The consumer’s mobile number on behalf of whom you are paying.
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
            'RecieverIdentifierType'    =>  $this->getIdentifierType("shortcode"),
            'Amount'                    =>  floor($amount), // remove decimal points
            'PartyA'                    =>  $this->mpesaShortcode,
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
        if(!$this->isValidUrl($data['QueueTimeOutURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid QueueTimeOutURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if (config('mpesa.debug')) {
            info('B2B Buy Goods Data: ' . json_encode($data));
            info('B2B Buy Goods Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /**
     *   This API is used to query the status of a B2B transaction.
     *  This is done by using the M-Pesa code and the phone number used in the transaction.
     *  @param $transactionId - This is a unique identifier of the transaction returned in the response of the original transaction.
     *  @param $remarks - Comments that are sent along with the transaction
     *  @param $originalConversationId - This is a unique identifier of the transaction returned in the response of the original transaction.
     *  @param $receiverShortCode - The shortcode of the organization that receives the transaction
     *  @param $remarks - Comments that are sent along with the transaction
     *  @param $originalConversationId - This is a unique identifier of the transaction returned in the response of the original transaction.
     */

    public function getTransactionStatus($transactionId, $identifierType, $remarks, $originalConversationId)
    {
        $url = $this->url . '/mpesa/transactionstatus/v1/query';
        $data = [
            'Initiator'                 =>  $this->initiatorName,
            'SecurityCredential'        =>  $this->securityCredential,
            'CommandID'                 =>  'TransactionStatusQuery',
            'TransactionID'             =>  $transactionId, //Organization Receiving the funds. e.g. LXXXXXX1234
            'PartyA'                    =>  $this->mpesaShortcode,
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
        if(!$this->isValidUrl($data['QueueTimeOutURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid QueueTimeOutURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if (config('mpesa.debug')) {
            info('Transaction Status Data: ' . json_encode($data));
            info('Transaction Status Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    // --------------------------------- QR Code ---------------------------------

    /**
     *   This  API is used to generate a QR code that can be used to make payments.
     *   The QR code can be scanned by the customer to make payments.
     *    @param $merchantName - Name of the Company/M-Pesa Merchant Name
     *    @param $refNo - Transaction Reference
     *    @param $amount - The total amount for the sale/transaction.
     *    @param $trxCode - Transaction Type. The supported types are:
     *       - BG: Pay Merchant (Buy Goods).
     *       - WA: Withdraw Cash at Agent Till.
     *       - PB: Paybill or Business number.
     *       - SM: Send Money(Mobile number)
     *       - SB: Sent to Business. Business number CPI in MSISDN format.
     *    @param $cpi - Credit Party Identifier. Can be a Mobile Number, Business Number, Agent Till, Paybill or Business number, or Merchant Buy Goods.
     *    @param $size - Size of the QR code image in pixels. QR code image will always be a square image.
     * 
     */

    public function dynamicQR($merchantName, $refNo, $amount, $trxCode, $cpi, $size)
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
        if (config('mpesa.debug')) {
            info('Dynamic QR Data: ' . json_encode($data));
            info('Dynamic QR Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    // --------------------------------- Bill Manager ---------------------------------

    /**
     *   The first step in the bill manager process is to optin to the service.
     *   This API is used to optin to the bill manager service.
     *   @param $email - The email address of the business
     *   @param $phoneNumber - The phone number of the business
     */

    public function billManagerOptin($email, $phoneNumber)
    {
        $url = $this->url . "/v1/billmanager-invoice/optin";
        $data = [
            'ShortCode' => $this->mpesaShortcode,
            'email' => $email,
            'officialContact' => $this->sanitizePhoneNumber($phoneNumber),
            'sendReminders' => '1', // [0 | 1] This field gives you the flexibility as a business to enable or disable sms payment reminders for invoices sent.
            'logo' => config('mpesa.confirmation_url'), // Optional : Image to be embedded in the invoices and receipts sent to your customer.
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
        if (config('mpesa.debug')) {
            info('Bill Manager Optin Data: ' . json_encode($data));
            info('Bill Manager Optin Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    /**
     *   This API is used to send an invoice to a customer.
     *   The invoice can be for goods or services.
     *  @param $reference - This is a unique invoice name on your system’s end. e.g. INV12345
     *  @param $billedTo - Full name of the person being billed e.g. John Doe
     *  @param $phoneNumber - Phone number of the person being billed e.g. 0712345678
     *  @param $billingPeriod - The period for which the invoice is being sent e.g. 1st Jan 2021 - 31st Jan 2021
     *  @param $invoiceName - A descriptive invoice name for what your customer is being billed. e.g. water bill
     *  @param $dueDate - This is the date you expect the customer to have paid the invoice amount.
     *  @param $amount - Total Invoice amount to be paid in Kenyan Shillings without special characters
     *  @param $items - These are additional billable items that you need included in your invoice. The invoice can have multiple items in the below format
     *       $items[
     *           'itemName' => 'Food',
     *           'amount' => 100, // Optional
     *       ]
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
        if (config('mpesa.debug')) {
            info('Send Invoice Data: ' . json_encode($data));
            info('Send Invoice Response Data: ' . $result);
        }

        // return the result
        return $result;
    }

    // --------------------------------- Tax Remittance ---------------------------------

    /**
     *   This API is used to tax remmitance to the government.
     *   The transaction moves money from the company’s account to the government account.
     *   @param $amount - The amount to be paid
     *   @param $receiverShortCode - The shortcode of the government receiving the payment
     *   @param $accountReference - The account number to be credited
     *   @param $remarks - Comments that are sent along with the transaction
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
            'PartyA' => $this->mpesaShortcode,
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
        if(!$this->isValidUrl($data['QueueTimeOutURL'])) {
            // throw an exception instead
            throw new \Exception('Invalid QueueTimeOutURL');
        }

        // make the request
        $result = $this->makeRequest($url, $data);

        // log the request and response data if debug is enabled on the config file
        if (config('mpesa.debug')) {
            info('Tax Remittance Data: ' . json_encode($data));
            info('Tax Remittance Response Data: ' . $result);
        }

        // return the result
        return $result;
    }
}

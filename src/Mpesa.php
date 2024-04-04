<?php

namespace Akika\LaravelMpesa;

use Akika\LaravelMpesa\Models\Token;
use Akika\LaravelMpesa\Traits\MpesaTrait;
use Carbon\Carbon;

class Mpesa
{
    use MpesaTrait;

    public $environment;
    public $url;
    public $consumerKey;
    public $consumerSecret;

    public $initiatorName;

    public function __construct()
    {
        $this->environment = config('mpesa.env');
        $this->consumerKey = config('mpesa.consumer_key');
        $this->consumerSecret = config('mpesa.consumer_secret');

        $this->initiatorName = config('mpesa.initiator_name');

        $this->url = $this->environment === 'sandbox' ? 'https://sandbox.safaricom.co.ke' : 'https://api.safaricom.co.ke';
    }

    public function index()
    {
        try {
            $this->getBalance();
        } catch (\Exception $e) {
            return response()->json([
                'status' => '01',
                'message' => $e->getMessage()
            ]);
        }
    }

    public function fetchToken()
    {
        $token = Token::first();

        if (!$token || $token->timeToExpiry() <= 30) {
            $token = json_decode($this->generateToken());
            Token::create([
                "access_token" => $token->access_token,
                "requested_at" => now(),
                "expires_at" => now()->addSeconds($token->expires_in)
            ]);
        }

        return $token->access_token;
    }

    public function getBalance()
    {
        $url = $this->url . '/mpesa/accountbalance/v1/query';
        $data = [
            'Initiator'             => $this->initiatorName, // This is the credential/username used to authenticate the transaction request
            'SecurityCredential'    => $this->generateCertificate(), // Base64 encoded string of the M-PESA short code and password, which is encrypted using M-PESA public key and validates the transaction on M-PESA Core system.
            'CommandID'             => 'AccountBalance', // A unique command is passed to the M-PESA system.
            'PartyA'                => config('mpesa.shortcode'), // The shortcode of the organization querying for the account balance.
            'IdentifierType'        => $this->getTransactionIdentifier("shortcode"), // Type of organization querying for the account balance.
            'Remarks'               => "balance", // String sequence of characters up to 100
            'QueueTimeOutURL'       => config('mpesa.balance_timeout_url'),
            'ResultURL'             => config('mpesa.balance_result_url')
        ];

        // check if $data['ResultURL'] is set and that it is a valid url
        if ($this->isValidUrl($data['ResultURL'])) {
            return $this->makeRequest($url, $data);
        } else {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }
    }

    public function c2bRegisterUrl()
    {
        $url = $this->url . '/mpesa/c2b/v1/registerurl';
        $data = [
            'ShortCode'     => config('mpesa.shortcode'),
            'ResponseType'   => 'Completed', // [Canceled | Completed] this is the default action value that determines what M-PESA will do in the scenario that your endpoint is unreachable or is unable to respond on time.
            'ConfirmationURL' => config('mpesa.stk_confirmation_url'), // This is the URL that receives payment notification once payment has been completed successfully on M-PESA.
            'ValidationURL' => config('mpesa.stk_validation_url') // This is the URL that is only used when a Merchant (Partner) requires to validate the details of the payment before accepting.
            // For example, a bank would want to verify if an account number exists in their platform before accepting a payment from the customer.
        ];

        return $this->makeRequest($url, $data);
    }

    public function stkPush($accountNumber, $phoneNumber, $amount, $transactionDesc = null)
    {
        $url = $this->url . '/mpesa/stkpush/v1/processrequest';
        $data = [
            'BusinessShortCode'     => config('mpesa.shortcode'),
            'Password'              => $this->generatePassword(), // base64.encode(Shortcode+Passkey+Timestamp)
            'Timestamp'             => Carbon::rawParse('now')->format('YmdHis'),
            'TransactionType'       => 'CustomerPayBillOnline',
            'Amount'                => floor($amount), // remove decimal points
            'PartyA'                => $this->sanitizePhoneNumber($phoneNumber),
            'PartyB'                => config('mpesa.shortcode'),
            'PhoneNumber'           => $this->sanitizePhoneNumber($phoneNumber),
            'AccountReference'      => $accountNumber, //Account Number for a paybill..Maximum of 12 Characters.,
            'TransactionDesc'       => $transactionDesc ? substr($transactionDesc, 0, 13) : 'STK Push', // Should not exceed 13 characters
            'CallBackURL'           => config('mpesa.stk_callback_url'),
        ];

        // check if $data['CallBackURL] is set and that it is a valid url
        if ($this->isValidUrl($data['CallBackURL'])) {
            return $this->makeRequest($url, $data);
        } else {
            // throw an exception instead
            throw new \Exception('Invalid CallBackURL');
        }
    }

    public function stkPushStatus($checkoutRequestID)
    {
        $url = $this->url . '/mpesa/stkpushquery/v1/query';
        $data = [
            'BusinessShortCode'     => config('mpesa.shortcode'),
            'Password'              => $this->generatePassword(),
            'Timestamp'             => Carbon::rawParse('now')->format('YmdHis'), // Date in format - YYYYMMDDHHmmss
            'CheckoutRequestID'     => $checkoutRequestID // This is a global unique identifier of the processed checkout transaction request.
        ];

        return $this->makeRequest($url, $data);
    }

    public function b2cTransaction($oversationId, $commandID, $msisdn, $amount, $remarks, $ocassion = null)
    {
        $url = $this->url . '/mpesa/b2c/v1/paymentrequest';
        /*
        * CommandID is a unique command that specifies B2C transaction type.
        *   SalaryPayment: This supports sending money to both registered and unregistered M-Pesa customers.
        *   BusinessPayment: This is a normal business to customer payment, supports only M-PESA registered customers.
        *   PromotionPayment: This is a promotional payment to customers. The M-PESA notification message is a congratulatory message. Supports only M-PESA registered customers.
        */
        $data = [
            'OriginatorConversationID' => $oversationId, // This is a unique string you specify for every API request you simulate
            'InitiatorName'         =>  $this->initiatorName, // This is an API user created by the Business Administrator of the M-PESA
            'SecurityCredential'    =>  $this->generateCertificate(), // This is the value obtained after encrypting the API initiator password.
            'CommandID'             =>  $commandID, // This is a unique command that specifies B2C transaction type.
            'Amount'                =>  floor($amount), // remove decimal points
            'PartyA'                =>  config('mpesa.shortcode'),
            'PartyB'                =>  $this->sanitizePhoneNumber($msisdn),
            'Remarks'               =>  $remarks,
            'Occassion'              =>  $ocassion ? substr($ocassion, 0, 100) : '', // Can be null
            'QueueTimeOutURL'       =>  config('mpesa.b2c_timeout_url'),
            'ResultURL'             =>  config('mpesa.b2c_result_url'),
        ];

        // check if $data['ResultURL] is set and that it is a valid url
        if ($this->isValidUrl($data['ResultURL'])) {
            return $this->makeRequest($url, $data);
        } else {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }
    }

    public function validatedB2CTransaction($commandID, $msisdn, $amount, $remarks, $idNumber, $ocassion = null)
    {
        $url = $this->url . '/mpesa/b2c/v1/paymentrequest';
        $data = [
            'InitiatorName'         =>  $this->initiatorName,
            'SecurityCredential'    =>  $this->generateCertificate(),
            'CommandID'             =>  $commandID,
            'Amount'                =>  floor($amount), // remove decimal points
            'PartyA'                =>  config('mpesa.shortcode'),
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
        if ($this->isValidUrl($data['ResultURL'])) {
            return $this->makeRequest($url, $data);
        } else {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }
    }

    /*
    *   This API enables you to pay bills directly from your business account to a pay bill number, or a paybill store. You can use this API to pay on behalf of a consumer/requester.
    *   The transaction moves money from your MMF/Working account to the recipient’s utility account.
    */

    public function b2bPaybill($destShortcode, $amount, $remarks, $accountNumber, $requester = null)
    {
        //DisburseFundsToBusiness
        $url = $this->url . '/mpesa/b2b/v1/paymentrequest';
        $data = [
            'Initiator'                 =>  $this->initiatorName,
            'SecurityCredential'        =>  $this->generateCertificate(),
            'CommandID'                 =>  'BusinessPayBill', // This specifies the type of transaction being performed. There are five allowed values on the API: BusinessPayBill, BusinessBuyGoods, DisburseFundsToBusiness, BusinessToBusinessTransfer or MerchantToMerchantTransfer.
            'SenderIdentifierType'      =>  $this->getTransactionIdentifier("shortcode"),
            'RecieverIdentifierType'    =>  $this->getTransactionIdentifier("shortcode"),
            'Amount'                    =>  floor($amount), // remove decimal points
            'PartyA'                    =>  config('mpesa.shortcode'),
            'PartyB'                    =>  $destShortcode,
            'AccountReference'          =>  $accountNumber, // The account number to be associated with the payment. Up to 13 characters.
            'Requester'                 =>  $this->sanitizePhoneNumber($requester), // Optional. The consumer’s mobile number on behalf of whom you are paying.
            'Remarks'                   =>  $remarks,
            'QueueTimeOutURL'           =>  config('mpesa.b2b_timeout_url'),
            'ResultURL'                 =>  config('mpesa.b2b_result_url')
        ];

        // check if $data['ResultURL] is set and that it is a valid url
        if ($this->isValidUrl($data['ResultURL'])) {
            return $this->makeRequest($url, $data);
        } else {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }
    }

    /*
    *   This API enables you to pay for goods and services directly from your business account to a till number, merchant store number or Merchant HO. You can also use this API to pay a merchant on behalf of a consumer/requestor. 
    *   The transaction moves money from your MMF/Working account to the recipient’s merchant account.
    */

    public function b2bBuyGoods($destShortcode, $amount, $remarks, $accountNumber, $requester = null)
    {
        //DisburseFundsToBusiness
        $url = $this->url . '/mpesa/b2b/v1/paymentrequest';
        $data = [
            'Initiator'                 =>  $this->initiatorName,
            'SecurityCredential'        =>  $this->generateCertificate(),
            'CommandID'                 =>  'BusinessBuyGoods', // This specifies the type of transaction being performed. There are five allowed values on the API: BusinessPayBill, BusinessBuyGoods, DisburseFundsToBusiness, BusinessToBusinessTransfer or MerchantToMerchantTransfer.
            'SenderIdentifierType'      =>  $this->getTransactionIdentifier("shortcode"),
            'RecieverIdentifierType'    =>  $this->getTransactionIdentifier("shortcode"),
            'Amount'                    =>  floor($amount), // remove decimal points
            'PartyA'                    =>  config('mpesa.shortcode'),
            'PartyB'                    =>  $destShortcode,
            'AccountReference'          =>  $accountNumber, // The account number to be associated with the payment. Up to 13 characters.
            'Requester'                 =>  $this->sanitizePhoneNumber($requester), // Optional. The consumer’s mobile number on behalf of whom you are paying.
            'Remarks'                   =>  $remarks,
            'QueueTimeOutURL'           =>  config('mpesa.b2b_timeout_url'),
            'ResultURL'                 =>  config('mpesa.b2b_result_url')
        ];

        // check if $data['ResultURL] is set and that it is a valid url
        if ($this->isValidUrl($data['ResultURL'])) {
            return $this->makeRequest($url, $data);
        } else {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }
    }

    public function getTransactionStatus($transactionId, $identifierType, $remarks, $originalConversationId)
    {
        $url = $this->url . '/mpesa/transactionstatus/v1/query';
        $data = [
            'Initiator'                 =>  $this->initiatorName,
            'SecurityCredential'        =>  $this->generateCertificate(),
            'CommandID'                 =>  'TransactionStatusQuery',
            'TransactionID'             =>  $transactionId, //Organization Receiving the funds. e.g. LXXXXXX1234
            'PartyA'                    =>  config('mpesa.shortcode'),
            'IdentifierType'            =>  $this->getTransactionIdentifier($identifierType),
            'Remarks'                   =>  $remarks,
            'Occasion'                  =>  NULL,
            'OriginalConversationID'    =>  $originalConversationId,
            'ResultURL'                 =>  config('mpesa.transaction_status_result_url'),
            'QueueTimeOutURL'           =>  config('mpesa.transaction_status_timeout_url'),
        ];

        // check if $data['ResultURL] is set and that it is a valid url
        if ($this->isValidUrl($data['ResultURL'])) {
            return $this->makeRequest($url, $data);
        } else {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }
    }

    /* Reverses a C2B M-Pesa transaction.
    * Once a customer pays and there is a need to reverse the transaction, the organization will use this API to reverse the amount.
    */
    public function reverse($transactionId, $amount, $receiverShortCode, $remarks)
    {
        $url = $this->url . '/mpesa/reversal/v1/request';
        $data = [
            'Initiator'                 =>  $this->initiatorName, // The name of the initiator to initiate the request.
            'SecurityCredential'        =>  $this->generateCertificate(),
            'CommandID'                 =>  'TransactionReversal',
            "TransactionID"             =>  $transactionId, // Payment transaction ID of the transaction that is being reversed. e.g. LKXXXX1234
            "Amount"                    =>  $amount, // The transaction amount
            "ReceiverParty"             =>  $receiverShortCode, // The organization that receives the transaction.
            "RecieverIdentifierType"    =>  $this->getTransactionIdentifier("shortcode"), // Type of organization that receives the transaction.
            "Remarks"                   =>  $remarks ?? "please", // Comments that are sent along with the transaction.
            "Occasion"                  =>  "", // Optional Parameter.
            "ResultURL"                 =>  config('mpesa.reversal_result_url'),
            "QueueTimeOutURL"           =>  config('mpesa.reversal_timeout_url'),
        ];

        // check if $data['ResultURL] is set and that it is a valid url
        if ($this->isValidUrl($data['ResultURL'])) {
            return $this->makeRequest($url, $data);
        } else {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }
    }

    public function dynamicQR($merchantName, $refNo, $amount, $trxCode, $cpi, $size)
    {
        $url = $this->url . '/mpesa/qrcode/v1/generate';
        /*
        *
        * Transaction Type. The supported types are:
        *    BG: Pay Merchant (Buy Goods).
        *    WA: Withdraw Cash at Agent Till.
        *    PB: Paybill or Business number.
        *    SM: Send Money(Mobile number)
        *    SB: Sent to Business. Business number CPI in MSISDN format.
        */

        $data = [
            'MerchantName' => $merchantName, // Name of the Company/M-Pesa Merchant Name
            'RefNo' => $refNo, // Transaction Reference
            'Amount' => floor($amount), // The total amount for the sale/transaction.
            'TrxCode' => $trxCode, // Transaction Type
            'CPI' => $cpi, // Credit Party Identifier. Can be a Mobile Number, Business Number, Agent Till, Paybill or Business number, or Merchant Buy Goods.
            'Size' => $size // Size of the QR code image in pixels. QR code image will always be a square image.
        ];
        return $this->makeRequest($url, $data);
    }

    public function taxRemittance($amount, $receiverShortCode, $accountReference, $remarks)
    {
        $url = $this->url . '/mpesa/b2b/v1/remittax';
        $data = [
            'Initiator' => $this->initiatorName,
            'SecurityCredential' => $this->generateCertificate(),
            'CommandID' => 'BusinessPayment',
            'SenderIdentifierType' => $this->getTransactionIdentifier("shortcode"),
            'RecieverIdentifierType' => $this->getTransactionIdentifier("shortcode"),
            'Amount' => floor($amount),
            'PartyA' => config('mpesa.shortcode'),
            'PartyB' => $receiverShortCode,
            'AccountReference' => $accountReference,
            'Remarks' => $remarks ?? 'Tax remittance',
            'QueueTimeOutURL' => config('mpesa.tax_remittance_timeout_url'),
            'ResultURL' => config('mpesa.tax_remittance_result_url')
        ];

        // check if $data['ResultURL] is set and that it is a valid url
        if ($this->isValidUrl($data['ResultURL'])) {
            return $this->makeRequest($url, $data);
        } else {
            // throw an exception instead
            throw new \Exception('Invalid ResultURL');
        }
    }

    // Gives the business and customers a one-stop end-to-end platform to send, receive, pay and reconcile all payments.
    public function billManagerOptin($email, $phoneNumber)
    {
        $url = "https://api.safaricom.co.ke/v1/billmanager-invoice/optin";
        $data = [
            'ShortCode' => config('mpesa.shortcode'),
            'email' => $email,
            'officialContact' => $this->sanitizePhoneNumber($phoneNumber),
            'sendReminders' => '1', // [0 | 1] This field gives you the flexibility as a business to enable or disable sms payment reminders for invoices sent.
            'logo' => config('mpesa.confirmation_url'), // Optional : Image to be embedded in the invoices and receipts sent to your customer.
            'callbackurl' => config('mpesa.bill_optin_callback_url')
        ];
        return $this->makeRequest($url, $data);
    }

    public function sendInvoice($reference, $billedTo, $phoneNumber, $billingPeriod, $invoiceName, $dueDate, $amount, $items)
    {
        $url = "https://api.safaricom.co.ke/v1/billmanager-invoice/single-invoicing";
        /*
        Items sample:
        $items[
            'itemName' => 'Food',
            'amount' => 100, // Optional
        ]
        */
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

        return $this->makeRequest($url, $data);
    }
}

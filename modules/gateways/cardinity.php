<?php
/**
 * Cardinity Gateway Module for WHMCS
 */


//autoload gateway functions
require_once __DIR__ . '/../../includes/gatewayfunctions.php';

//Autoload Cardinity SDK
require_once __DIR__."/cardinity/vendor/autoload.php";


use Cardinity\Client;
use Cardinity\Exception;
use Cardinity\Method\Payment;
use Cardinity\Method\Refund;
use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define gateway metadata
 *
 * @return array
 */
function cardinity_MetaData()
{
    return array(
        'DisplayName' => 'Cardinity',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => false,
        'TokenisedStorage' => true,
    );
}

/**
 * Define gateway configuration
 *
 * @return array
 */
function cardinity_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Cardinity',
        ),

        'liveConsumerKey' => array(
            'FriendlyName' => 'Live Consumer Key',
            'Type' => 'text',
            'Size' => '100',
            'Default' => '',
            'Description' => 'Enter live consumer key here',
        ),

        'liveConsumerSecret' => array(
            'FriendlyName' => 'Live Consumer Secret',
            'Type' => 'text',
            'Size' => '100',
            'Default' => '',
            'Description' => 'Enter live consumer secret here',
        ),


        'gatewayMode' => array(
            'FriendlyName' => 'Gateway Mode',
            'Type' => 'radio',
            'Options' => 'Test,Live',
        ),

        'testConsumerKey' => array(
            'FriendlyName' => 'Test Consumer Key',
            'Type' => 'text',
            'Size' => '100',
            'Default' => '',
            'Description' => 'Enter test consumer key here',
        ),

        'testConsumerSecret' => array(
            'FriendlyName' => 'Test Consumer Secret',
            'Type' => 'text',
            'Size' => '100',
            'Default' => '',
            'Description' => 'Enter test consumer secret here',
        ),
    );
}

/**
 * Refund transaction
 *
 * @param array $params Payment gateway module parameters
 * @return void
 */
function cardinity_refund($params)
{
    //Create Cardinity client
    $client = createCardinityClient($params);

    $method = new Refund\Create(
        $params['transid'],
        floatval($params['amount'])
    );

    try {
        $result = $client->call($method);
    } catch (Exception\Unauthorized $exception) {
        return createWhmcsReturnArray('error', 'Cardinity Gateway authentication error: Missing or invalid API keys');
    } catch (Exception\Declined $exception) {
        return createWhmcsReturnArray('declined', 'Error: ' . $exception->getErrorsAsString());
    } catch (Exception\Request $exception) {
        return createWhmcsReturnArray('error', 'Error: ' . $exception->getErrorsAsString());
    } catch (Exception\Runtime $exception) {
        return createWhmcsReturnArray('error', 'Error: ' . $exception->getMessage());
    }

    $status = $result->getStatus();

    if ($status == 'approved') {
        return createWhmcsReturnArray('success', $result, $result->getId());
    } else {
        return createWhmcsReturnArray('error', $result, $result->getId());
    }
}

//rename / remove this function if using external
function processInternalPayment($params){

    //Create Cardinity client
    $client = createCardinityClient($params);
    //Cardinity API accepts order id with minimum length of 2.
    $orderId = str_pad($params['invoiceid'], 2, '0', STR_PAD_LEFT);

    //Cardinity API accepts payment amount as float value
    $amount = floatval($params['amount']);

    $userAgent = explode("; ", $_SERVER['HTTP_USER_AGENT']);



    //Capture function does not guarantee entered CVV. In case it is not entered, use recurring payment terminal
    if (empty($params['cccvv'])) {
        $paymentMethod = Payment\Create::RECURRING;
        $paymentInstrument = [
            'payment_id' => getCustomerLastPaymentToken($params['invoiceid']),
        ];
    } else {
        //Concatenate card holders first and last name
        $holder = $params['clientdetails']['firstname'] . ' ' . $params['clientdetails']['lastname'];

        //WHMCS stores card expiration information in MMYY format.
        $cardExpMonth = intval(substr($params['cardexp'], 0, 2));

        //Convert year two-digit format to 4-digit format
        $year = substr($params['cardexp'], 2, 2); //Get year in WHMCS format
        $dateTime = DateTime::createFromFormat('y', $year); //Create Date object
        $cardExpYear = intval($dateTime->format('Y')); //Retrieve date in 4-digit format

        $paymentMethod = Payment\Create::CARD;
        $paymentInstrument = [
            'pan' => $params['cardnum'],
            'exp_year' => $cardExpYear,
            'exp_month' => $cardExpMonth,
            'cvc' => $params['cccvv'],
            'holder' => $holder,
        ];


        //decode from json
        $securedCookieArray = json_decode(base64_decode($_COOKIE['cardinity_browser_info']), true);

        //pluck signature
        $signatureGot = $securedCookieArray['signature'];
        unset($securedCookieArray['signature']);

        //generate new signature
        $browser_info_string = implode("",$securedCookieArray);
        $signature = hash_hmac('sha256', $browser_info_string, $_SERVER['HTTP_USER_AGENT']);

        if($signature == $signatureGot){
            //signature matched
            $browserInfoCookie = $securedCookieArray;
        }else{
            $browserInfoCookie = array();
        }

        /*
        * The actual credit card info form is handled by whmcs and is encoded
        * Unable to get the brower info variables from there.
        */
        $threeds2_data = [
            "notification_url" => $params['systemurl'] . 'modules/gateways/callback/cardinity.php',
            "browser_info" => [
                "accept_header" => "text/html",
                "browser_language" => $browserInfoCookie['browser_language'] ?? 'en-US',
                "screen_width" => (int) $browserInfoCookie['screen_width'] ?? 1920,
                "screen_height" => (int) $browserInfoCookie['screen_height'] ?? 1040,
                'challenge_window_size' => $browserInfoCookie['challenge_window_size'] ?? "full-screen",
                "user_agent" => $_SERVER['HTTP_USER_AGENT'],
                "color_depth" => (int) $browserInfoCookie['color_depth'] ?? 24,
                "time_zone" => (int) $browserInfoCookie['time_zone'] ?? -60
            ],
        ];


    }

    //prepare parameters
    $paymentParameters = [
        'amount' => $amount,
        'currency' => $params['currency'],
        'settle' => true,
        'description' => $params['description'],
        'order_id' => $orderId,
        'country' => $params['clientdetails']['countrycode'],
        'payment_method' => $paymentMethod,
        'payment_instrument' => $paymentInstrument,
    ];

    //if available add 3ds2 data
    if(isset($threeds2_data)){
        $paymentParameters['threeds2_data'] = $threeds2_data;
    }

    //Create cardinity API payment method
    $method = new Payment\Create($paymentParameters);

    //Get result from cardinity API
    try {
        $result = $client->call($method);
    } catch (Exception\Unauthorized $exception) {
        return createWhmcsReturnArray('error', 'Cardinity Gateway authentication error: Missing or invalid API keys');
    } catch (Exception\Declined $exception) {
        return createWhmcsReturnArray('declined', 'Error: ' . $exception->getErrorsAsString());
    } catch (Exception\Request $exception) {
        return createWhmcsReturnArray('error', 'Error: ' . $exception->getErrorsAsString());
    } catch (Exception\Runtime $exception) {
        return createWhmcsReturnArray('error', 'Error: ' . $exception->getMessage());
    }

    //Get payment status
    $status = $result->getStatus();

    if ($status == 'approved') {
        addRemoteToken($params['invoiceid'], $result->getId());

        //return WHMCS responce
        return createWhmcsReturnArray('success', $result, $result->getId());
    } else if ($status == 'pending') {

        if ($result->isThreedsV2()) {
            //3D secure v2 authorization pending
            $acs_url = $result->getThreeds2data()->getAcsUrl();
            $creq = $result->getThreeds2data()->getCreq();
            $threeDSSessionData = $params['invoiceid'] . ',' . $result->getId();

            //Build the 3dsv2 request form
            $requestForm = '<html>
                <head>
                    <title>Request Example | Hosted Payment Page</title>
                    <script type="text/javascript">setTimeout(function() { document.getElementById("3dsecureform").submit(); }, 5000);</script>
                </head>
                <body>
                    <div style="text-align: center; width: 300px; position: fixed; top: 30%; left: 50%; margin-top: -50px; margin-left: -150px;">
                        <h2>You will be redirected for 3D secure verification shortly. </h2>
                        <p>If browser does not redirect after 5 seconds, press Submit</p>
                        <form id="3dsecureform" method="post" action="' . $acs_url . '">
                            <button type=submit>Click Here</button>
                            <input type="hidden" name="creq" value="' . $creq . '" />
                            <input type="hidden" name="threeDSSessionData" value="' . $threeDSSessionData . '"/>
                        </form>
                    </div>
                </body>
            </html>';

            echo $requestForm;
            //we dont want to do anything else. just show html form and redirect
            exit();

        } else {
            //3D secure authorization pending
            $url = $result->getAuthorizationInformation()->getUrl();
            $pareq = $result->getAuthorizationInformation()->getData();
            $termurl = $params['systemurl'] . 'modules/gateways/callback/cardinity.php';
            $md = $params['invoiceid'] . ',' . $result->getId();

            $htmlOutput = "<div style='text-align: center; width:300px; position: fixed; top: 30%; left: 50%; margin-top: -50px; margin-left: -150px;'>";
            $htmlOutput .= '<h2>You will be redirected for 3ds verification shortly. </h2>';
            $htmlOutput .= '<p>If browser does not redirect after 5 seconds, press Submit</p>';
            $htmlOutput .= '<form id="3dsecureform" method="post" action="' . $url . '">';
            $htmlOutput .= '<input type="hidden" name="PaReq" value="' . $pareq . '" />';
            $htmlOutput .= '<input type="hidden" name="TermUrl" value="' . $termurl . '"/>';
            $htmlOutput .= '<input type="hidden" name="MD" value="' . $md . '"/>';
            $htmlOutput .= '<input type="submit" value="Submit" />';
            $htmlOutput .= '</form>';
            $htmlOutput .= '<script type="text/javascript">setTimeout(function() { document.getElementById("3dsecureform").submit(); }, 5000);</script>';
            $htmlOutput .= '</div>';

            echo $htmlOutput;
            //we dont want to do anything else. just show html form and redirect
            exit();
        }



    } else {
        //Should never happen
        return createWhmcsReturnArray('error', $result, $result->getId());
    }
}


/***
 * Replace this function  for external
 */
function cardinity_capture($params){
    return processInternalPayment($params);
}



/**
 * Create a cardinity client object
 *
 * @return Cardinity\Client cardinity client
 */
function createCardinityClient($params)
{
    //Select the api credentials by gateway mode
    $gatewayMode = $params['gatewayMode'];
    if ($gatewayMode == 'Test') {
        $consumerKey = $params['testConsumerKey'];
        $consumerSecret = $params['testConsumerSecret'];
    } else {
        $consumerKey = $params['liveConsumerKey'];
        $consumerSecret = $params['liveConsumerSecret'];
    }

    //Create Cardinity Client
    $client = Client::create([
        'consumerKey' => $consumerKey,
        'consumerSecret' => $consumerSecret,
    ]);

    return $client;
}

/**
 * Create an array that indicates the status of the payment
 *
 * @param [type] $status payment status - success, decline or error
 * @param [type] $rawData - raw payment data
 * @param string $transactionId - transaction ID
 * @return array
 */
function createWhmcsReturnArray($status, $rawData, $transactionId = '')
{
    return array(
        'status' => $status,
        'rawdata' => $rawData,
        'transid' => $transactionId
    );
}

/**
 * Assign remote token for an added credit card
 *
 * @param [type] $invoiceId invoice id, used to get the user information
 * @param [type] $remoteTokenID Cardinity payment ID, used as a token for recurring transactions
 * @return void
 */
function addRemoteToken($invoiceId, $remoteTokenID)
{
    $user = Capsule::table('tblinvoiceitems')->select('userid')
        ->where('invoiceid', $invoiceId)
        ->first();

    Capsule::table('tblclients')
        ->where('id', $user->userid)
        ->update([
           "gatewayid" => $remoteTokenID
        ]);
}

/**
 * Find customers gateway token from invoice
 *
 * @param [type] $invoiceId
 * @return string Cardinity payment ID, used as a token for recurring transactions
 */
function getCustomerLastPaymentToken($invoiceId)
{

    $invoice = Capsule::table('tblinvoiceitems')->select('userid')
        ->where('invoiceid', $invoiceId)
        ->first();

    $account = Capsule::table('tblaccounts')
        ->select('transid')
        ->where('userid',$invoice->userid)
        ->where('gateway','cardinity')
        ->latest('id')
        ->first();

    return $account->transid;
}





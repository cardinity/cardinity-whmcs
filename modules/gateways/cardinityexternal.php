<?php
/**
 * Cardinity Gateway Module for WHMCS
 */

//Autoload Cardinity SDK
require_once "cardinity/vendor/autoload.php";
//autoload gateway functions
require_once __DIR__ . '/../../includes/gatewayfunctions.php';


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
function cardinityexternal_MetaData()
{
    return array(
        'DisplayName' => 'Cardinity Hosted Payment',
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
function cardinityexternal_config()
{
    return array(
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Cardinity External',
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

        'projectId' => array(
            'FriendlyName' => 'Cardinity Project ID',
            'Type' => 'text',
            'Size' => '100',
            'Default' => '',
        ),

        'projectSecret' => array(
            'FriendlyName' => 'Cardinity Project Secret',
            'Type' => 'text',
            'Size' => '100',
            'Default' => '',
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
function cardinityexternal_refund($params)
{
    //Create Cardinity client
    $client = createCardinityExternalClient($params);

    $method = new Refund\Create(
        $params['transid'],
        floatval($params['amount'])
    );

    try {
        $result = $client->call($method);
    } catch (Exception\Unauthorized $exception) {
        return createWhmcsReturnArrayExternal('error', 'Cardinity Gateway authentication error: Missing or invalid API keys');
    } catch (Exception\Declined $exception) {
        return createWhmcsReturnArrayExternal('declined', 'Error: ' . $exception->getErrorsAsString());
    } catch (Exception\Request $exception) {
        return createWhmcsReturnArrayExternal('error', 'Error: ' . $exception->getErrorsAsString());
    } catch (Exception\Runtime $exception) {
        return createWhmcsReturnArrayExternal('error', 'Error: ' . $exception->getMessage());
    }

    $status = $result->getStatus();

    if ($status == 'approved') {
        return createWhmcsReturnArrayExternal('success', $result, $result->getId());
    } else {
        return createWhmcsReturnArrayExternal('error', $result, $result->getId());
    }
}



//rename this to cardinityexternal_link to use external payment
function processExternalPayment($params){
       
    //Create Cardinity client
    $client = createCardinityExternalClient($params);
    //Cardinity API accepts order id with minimum length of 2.
    $orderId = str_pad($params['invoiceid'], 2, '0', STR_PAD_LEFT);

    //Cardinity API accepts payment amount as float value to 2 decimal places
    $amount = number_format((float)$params['amount'], 2, '.', '') ;

    $cancelUrl = $params['systemurl'] . 'modules/gateways/callback/cardinityexternal.php';
    $returnUrl = $params['systemurl'] . 'modules/gateways/callback/cardinityexternal.php';


    $attributes = [
        "amount" => $amount,
        "currency" => $params['currency'],
        "country" => $params['clientdetails']['countrycode'],
        "order_id" => $orderId,
        "description" => "$params[invoiceid]",
        "project_id" => $params['projectId'],
        "cancel_url" => $cancelUrl,
        "return_url" => $returnUrl,
    ];

    ksort($attributes);

    $message = '';
    foreach($attributes as $key => $value) {
        $message .= $key.$value;
    }

    $signature = hash_hmac('sha256', $message, $params['projectSecret']);

    
    //Build the external request form
    $requestForm = '<html>
        <head>
            <title>Request Example | Hosted Payment Page</title>
            <script type="text/javascript">setTimeout(function() { document.getElementById("externalPaymentForm").submit(); }, 5000);</script>
        </head>
        <body>
            <div style="text-align: center; width: 300px; position: fixed; top: 30%; left: 50%; margin-top: -50px; margin-left: -150px;">
                <h2>You will be redirected to external gateway shortly. </h2>
                <p>If browser does not redirect after 5 seconds, press Submit</p>
                <form id="externalPaymentForm" name="checkout" method="POST" action="https://checkout.cardinity.com">                    
                    <button type=submit>Click Here</button>
                    <input type="hidden" name="amount" value="' . $attributes['amount'] . '" />
                    <input type="hidden" name="cancel_url" value="' . $attributes['cancel_url'] . '" />
                    <input type="hidden" name="country" value="' . $attributes['country'] . '" />
                    <input type="hidden" name="currency" value="' . $attributes['currency'] . '" />
                    <input type="hidden" name="description" value="' . $attributes['description'] . '" />
                    <input type="hidden" name="order_id" value="' . $attributes['order_id'] . '" />
                    <input type="hidden" name="project_id" value="' . $attributes['project_id'] . '" />
                    <input type="hidden" name="return_url" value="' . $attributes['return_url'] . '" />
                    <input type="hidden" name="signature" value="' . $signature . '" />
                </form>
            </div>
        </body>
        </html>';

    echo $requestForm;
    //we dont want to do anything else. just show html form and redirect
    exit();
}



/***
 * Replace this function  for external
 */
function cardinityexternal_link($params){
    return processExternalPayment($params);
}



/**
 * Create a cardinity client object
 *
 * @return Cardinity\Client cardinity client
 */
function createCardinityExternalClient($params)
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
function createWhmcsReturnArrayExternal($status, $rawData, $transactionId = '')
{
    return array(
        'status' => $status,
        'rawdata' => $rawData,
        'transid' => $transactionId,
    );
}

/**
 * Assign remote token for an added credit card
 *
 * @param [type] $invoiceId invoice id, used to get the user information
 * @param [type] $remoteTokenID Cardinity payment ID, used as a token for recurring transactions
 * @return void
 */

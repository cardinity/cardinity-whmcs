<?php
/**
 * Cardinity Gateway Module for WHMCS
 */

//autoload gateway functions
require_once __DIR__ . '/../../includes/gatewayfunctions.php';

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
      
    );
}



//rename this to cardinityexternal_link to use external payment
function processExternalPayment($params){
       
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

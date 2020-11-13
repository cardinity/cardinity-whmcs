<?php
/**
 * Cardinity Payment Gateway 3D Secure Callback handler for WHMCS
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';


//Autoload Cardinity SDK
require_once __DIR__ . '/../cardinity/vendor/autoload.php';

use \Cardinity\Client;
use \Cardinity\Method\Payment;
use \Cardinity\Method\Refund;
use \Cardinity\Exception;
use WHMCS\Database\Capsule;

//$whmcs->load_function('gateway');
//$whmcs->load_function('invoice');

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}


//Select the api credentials by gateway mode
$gatewayMode = $gatewayParams['gatewayMode'];
if ($gatewayMode == 'Test') {
    $consumer_key = $gatewayParams['testConsumerKey'];
    $consumer_secret = $gatewayParams['testConsumerSecret'];
} else {
    $consumer_key = $gatewayParams['liveConsumerKey'];
    $consumer_secret = $gatewayParams['liveConsumerSecret'];
}

//Create Cardinity Client
$client = Client::create([
    'consumerKey' => $consumer_key,
    'consumerSecret' => $consumer_secret,
]);




/**
 * External Callback
 */
$message = '';
ksort($_POST);

foreach ($_POST as $key => $value) {
    if ($key == 'signature') continue;
    $message .= $key . $value;
}



//$transactionId = $_POST['id'];

$signature = hash_hmac('sha256', $message, $gatewayParams['projectSecret']);

$invoiceId = $_POST['description'];
$transactionId = $_POST['id'];

/**
 * Validate Callback Invoice ID.
 *
 * Checks invoice ID is a valid invoice number. Note it will count an
 * invoice in any status as valid.
 *
 * Performs a die upon encountering an invalid Invoice ID.
 *
 * Returns a normalised invoice ID.
 *
 * @param int $invoiceId Invoice ID
 * @param string $gatewayName Gateway Name
 */
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

 /**
 * Check Callback Transaction ID.
 *
 * Performs a check for any existing transactions with the same given
 * transaction number.
 *
 * Performs a die upon encountering a duplicate.
 *
 * @param string $transactionId Unique Transaction ID
 */
checkCbTransID($transactionId);  
   

//Verify response is not tampered
if ($signature == $_POST['signature']) {
    // check if payment is approved
    if ($_POST['status'] == "approved") {           
        //Payment Accepted                    
        $isVerificationSuccessful = true;
        addInvoicePayment(
            $invoiceId,
            $transactionId,
            $_POST['amount'],
            0, // Payment Fee
            $gatewayModuleName
        );
    }
} 

//Finished callback, redirect to whmcs
callback3DSecureRedirect($invoiceId, $isVerificationSuccessful);


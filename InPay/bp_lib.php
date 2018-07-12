<?php

function bpCurl ($url, $post = false) {
    $curl = curl_init($url);
    if ($post) {
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
    }

    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // verify certificate
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // check existence of CN and verify that it matches hostname
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
    curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);

    $responseString = curl_exec($curl);

    if ($responseString == false) {
        $response = curl_error($curl);
    } else {
        $response = json_decode($responseString, true);
    }
    curl_close($curl);
    return $response;
}

// $orderId: Used to display an orderID to the buyer. In the account summary view, this value is used to
// identify a ledger entry if present.
//
// $price: by default, $price is expressed in the currency you set in bp_options.php.  The currency can be 
// changed in $options.
//
// $posData: this field is included in status updates or requests to get an invoice.  It is intended to be used by
// the merchant to uniquely identify an order associated with an invoice in their system.  Aside from that, Bit-Pay does
// not use the data in this field.  The data in this field can be anything that is meaningful to the merchant.
//
// $options keys can include any of: 
// ('apiKey', 'amount', 'currency', 'orderCode', 'callbackUrl', 
//		'customerName', 'customerAddress1', 'customerAddress2', 'customerCity', 'customerState', 'customerZip', 'customerEmail', 'customerPhone',
//		'successUrl', 'failUrl', 'minConfirmations');
function bpCreateInvoice ($price, $orderId, $options = array()) {

    $post = array();

    $options['orderCode'] = $orderId;
    $options['amount'] = $price;

    $postOptions = array(
        'apiKey',
        'amount',
        'currency',
        'orderCode',
        'callbackUrl',
        'customerName',
        'customerAddress1',
        'customerAddress2',
        'customerCity',
        'customerState',
        'customerZip',
        'customerEmail',
        'customerPhone',
        'successUrl',
        'failUrl',
        'minConfirmations'
    );
    foreach ($postOptions as $o) {
        if (array_key_exists($o, $options)) {
            $post[$o] = $options[$o];
        }
    }
    $postData = "";
    foreach ($post as $key => $value) {
        $postData .= $key . "=" . urlencode($value) . '&';
    }
    $postData = substr($postData, 0, strlen($postData) - 1);

    $response = bpCurl('https://apitest.inpay.pl/invoice/create', $postData);
    if (is_string($response)) {
        return array('messageType' => 'error', 'message' => $response);
    }

    return $response;
}

// Call from your notification handler to convert $_POST data to an object containing invoice data
function bpVerifyNotification ($secretApiKey = false)
{
    $apiHash = $_SERVER['HTTP_API_HASH'];
    $query = http_build_query($_POST);
    $hash = hash_hmac("sha512", $query, $secretApiKey);
    return $apiHash == $hash;
}
?>
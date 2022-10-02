<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function vpay_MetaData() {
    return array(
        'DisplayName' => 'Virtual Payments',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function vpay_config() {
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'VPAY',
        ),
        'apiKey' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '128',
            'Default' => 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX',
            'Description' => 'Please enter your Virtual Payments API key',
        ),
		'secretKey' => array(
            'FriendlyName' => 'Secret API Key',
            'Type' => 'password',
            'Size' => '128',
            'Default' => 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX',
            'Description' => 'Please enter your Virtual Payments Secret API key',
        ),
    );
}

function vpay_link($params) {
    $amount = $params['amount'];
    $currency = $params['currency'];
    $total = calculateUSD($amount, $currency);

    $data = array(
        "key"        =>  $params['secretKey'],
        "customer"         =>  $params['clientdetails']['email'],
        "amount"        =>  $total,
        "webhook_url"    =>  $params['systemurl'] . 'modules/gateways/callback/vpay.php',
        "return_url"     =>  $params['returnurl'],
        "internalTransactionId"      =>  $params['invoiceid'],
		"receivingCurrency" => "BTC"
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://app.virtual-payments.com/api/v1/pay/create");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($raw);

    if (!isset($response->data->code) || $response->status != "success") {
        return false;
    }

    $redirectURL = "https://app.virtual-payments.com/checkout/" . $response->data->code;

    return '<a href="' . $redirectURL . '" class="btn">Pay with VPAY.</a>';
}

function vpay_refund($params) {
    return array(
        'status' => "error",
        'rawdata' => "",
        'transid' => "",
        'fees' => 0,
    );
}

function vpay_cancelSubscription($params) {
    return array(
        'status' => "error",
        'rawdata' => "",
    );
}

function calculateUSD($amount, $currency) {
    $amount = (float)$amount;
    $currency = strtoupper($currency);

    if ($amount <= 0) {
        return $amount;
    }

    if (!$currency || $currency === null || $currency === "") {
        return $amount;
    }

    $url = "https://app.virtual-payments.com/api/rates/" . $currency . "USD";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $raw = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($raw);

    if (!$raw || $raw === null) {
        return $amount;
    }

    if (!isset($response->status) || $response->status !== "success") {
        return $amount;
    }

    $multiplier = (float)$response->rate;

    if ($multiplier <= 0) {
        return $amount;
    }

    return $amount * $multiplier;
}

?>
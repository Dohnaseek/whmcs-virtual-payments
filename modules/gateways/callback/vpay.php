<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayModuleName = basename(__FILE__, '.php');
$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$rawResponse = file_get_contents("php://input");
$response = json_decode($rawResponse);

if (!isset($response->data->internalTransactionId)) {
    logTransaction($gatewayParams['name'], $rawResponse, 'Data not sent correctly');
    die('Data not sent correctly');
};

$invoiceId = $response->data->internalTransactionId;
$transactionId = $response->data->transaction_code;
$paymentAmount = $response->data->amount;

if ($response->data->secretKey !== $gatewayParams['secretKey']) {
    logTransaction($gatewayParams['name'], $rawResponse, 'API Key Verification Failure');
    die('API Key Verification Failure');
}

if ($response->data->status !== "completed") {
    logTransaction($gatewayParams['name'], $rawResponse, 'Transaction not paid');
    die('Transaction not paid');
}

$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
checkCbTransID($transactionId);
logTransaction($gatewayParams['name'], $rawResponse, $transactionStatus);
addInvoicePayment($invoiceId, $transactionId, 0, 0, $gatewayModuleName);

?>
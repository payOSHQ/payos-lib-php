<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PayOS\Exceptions\WebhookException;
use PayOS\Models\Webhooks\Webhook;
use PayOS\PayOS;

/**
 * Example demonstrating webhook operations:
 * - Confirm/register webhook URL
 * - Verify webhook data received from PayOS
 *
 * Note: You can view the history of webhook in https://my.payos.vn
 */

// Mock webhook data for demonstration
// In real usage, this would come from PayOS webhook POST request
$mockWebhookDataArray = [
    'code' => '00',
    'desc' => 'success',
    'success' => true,
    'data' => [
        'orderCode' => 123,
        'amount' => 3000,
        'description' => 'VQRIO123',
        'accountNumber' => '12345678',
        'reference' => 'TF230204212323',
        'transactionDateTime' => '2023-02-04 18:25:00',
        'currency' => 'VND',
        'paymentLinkId' => '124c33293c43417ab7879e14c8d9eb18',
        'code' => '00',
        'desc' => 'Thành công',
        'counterAccountBankId' => '',
        'counterAccountBankName' => '',
        'counterAccountName' => '',
        'counterAccountNumber' => '',
        'virtualAccountName' => '',
        'virtualAccountNumber' => '',
    ],
    'signature' => '',
];

try {
    $payos = new PayOS();

    // Calculate the webhook signature from mock data, this signature will be sent by PayOS
    // You can using this method to manually verify the webhook data
    $crypto = $payos->getCrypto();
    $mockWebhookDataArray['signature'] = $crypto->createSignatureFromObj(
        $mockWebhookDataArray['data'],
        $payos->getChecksumKey()
    );

    // 1. Confirm/Register webhook URL
    echo "Registering webhook URL...\n";
    $webhookUrl = 'https://your-domain.com/payos-webhook';

    try {
        $confirmResult = $payos->webhooks->confirm($webhookUrl);
        echo "Webhook registered successfully:\n";
        echo "  Webhook URL: {$confirmResult->webhookUrl}\n";
        echo "  Account Name: {$confirmResult->accountName}\n";
        echo "  Account Number: {$confirmResult->accountNumber}\n";
        echo "  Name: {$confirmResult->name}\n";
        echo "  Short Name: {$confirmResult->shortName}\n";
    } catch (WebhookException $error) {
        echo "Webhook registration failed: {$error->getMessage()}\n";
    }

    // 2. Verify webhook data (simulate receiving webhook from PayOS)
    echo "\nVerifying webhook data...\n";

    try {
        // In a real application, you would receive this data from PayOS
        // You can pass either an array or a Webhook object
        $verifiedData = $payos->webhooks->verify($mockWebhookDataArray);

        echo "Webhook verified successfully:\n";
        echo "Webhook data received:\n";
        echo "  Order Code: {$verifiedData->orderCode}\n";
        echo "  Amount: {$verifiedData->amount}\n";
        echo "  Description: {$verifiedData->description}\n";
        echo "  Account Number: {$verifiedData->accountNumber}\n";
        echo "  Reference: {$verifiedData->reference}\n";
        echo "  Transaction DateTime: {$verifiedData->transactionDateTime}\n";
        echo "  Currency: {$verifiedData->currency}\n";
        echo "  Payment Link ID: {$verifiedData->paymentLinkId}\n";
        echo "  Code: {$verifiedData->code}\n";
        echo "  Description: {$verifiedData->desc}\n";

        // Process the payment confirmation
        echo "\nProcessing payment confirmation...\n";
        // Here you would update your database, send confirmation emails, etc.
    } catch (WebhookException $error) {
        echo "Webhook verification failed: {$error->getMessage()}\n";
        echo "This might be a fraudulent webhook request\n";
    }
} catch (Exception $error) {
    echo "Unexpected error: {$error->getMessage()}\n";
}

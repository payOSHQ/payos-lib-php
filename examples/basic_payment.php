<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PayOS\Exceptions\APIException;
use PayOS\Models\V2\PaymentRequests\CreatePaymentLinkRequest;
use PayOS\PayOS;

/**
 * Example: Using payOS
 */
$client = new PayOS(
    clientId: getenv('PAYOS_CLIENT_ID') ?: 'test-client-id',
    apiKey: getenv('PAYOS_API_KEY') ?: 'test-api-key',
    checksumKey: getenv('PAYOS_CHECKSUM_KEY') ?: 'test-checksum-key',
);

function createPaymentLinkExample(PayOS $client): void
{
    $paymentData = new CreatePaymentLinkRequest(
        orderCode: time(),
        amount: 10000,
        description: "test payment",
        returnUrl: "https://your-url.com/success",
        cancelUrl: "https://your-url.com/cancel"
    );

    try {
        $result = $client->paymentRequests->create($paymentData);

        echo "Checkout URL: {$result->checkoutUrl}\n";
    } catch (APIException $e) {
        echo "API Error: {$e->getMessage()}\n";
        echo "Status: {$e->status}\n";
        echo "Code: {$e->errorCode}\n\n";
    }
}

createPaymentLinkExample($client);

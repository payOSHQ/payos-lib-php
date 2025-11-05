<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PayOS\Exceptions\APIException;
use PayOS\Models\V1\Payouts\Batch\PayoutBatchItem;
use PayOS\Models\V1\Payouts\Batch\PayoutBatchRequest;
use PayOS\Models\V1\Payouts\PayoutRequest;
use PayOS\PayOS;

try {
    // Initialize payOS with payout credentials
    $payos = new PayOS(
        clientId: getenv('PAYOS_PAYOUT_CLIENT_ID') ?: null,
        apiKey: getenv('PAYOS_PAYOUT_API_KEY') ?: null,
        checksumKey: getenv('PAYOS_PAYOUT_CHECKSUM_KEY') ?: null
    );

    echo "=== Creating a single payout ===\n";

    try {
        $referenceId = 'payout_' . time();
        $payoutRequest = new PayoutRequest(
            referenceId: $referenceId,
            amount: 10000,
            description: 'Test payout',
            toBin: '970422',
            toAccountNumber: '0123456789',
            category: ['salary']
        );

        $payout = $payos->payouts->create($payoutRequest);
        echo "Payout created successfully!\n";
        echo "  ID: {$payout->id}\n";
        echo "  Reference ID: {$payout->referenceId}\n";
        echo "  Approval State: {$payout->approvalState->value}\n";
        echo "  Created At: {$payout->createdAt}\n\n";
    } catch (APIException $error) {
        echo "Failed to create payout: {$error->getMessage()}\n\n";
    }

    echo "=== Creating a batch payout ===\n";

    try {
        $batchReferenceId = 'batch_payout_' . time();
        $batchItems = [
            new PayoutBatchItem(
                referenceId: "{$batchReferenceId}_1",
                amount: 2000,
                description: 'Batch payout 1',
                toBin: '970422',
                toAccountNumber: '0123456789'
            ),
            new PayoutBatchItem(
                referenceId: "{$batchReferenceId}_2",
                amount: 3000,
                description: 'Batch payout 2',
                toBin: '970422',
                toAccountNumber: '0123456789'
            ),
        ];

        $batchRequest = new PayoutBatchRequest(
            referenceId: $batchReferenceId,
            payouts: $batchItems,
            category: ['salary'],
            validateDestination: true
        );

        $estimate = $payos->payouts->estimateCredit($batchRequest);
        echo "Estimated credit required: {$estimate->estimateCredit}\n\n";

        $batchPayout = $payos->payouts->batch->create($batchRequest);
        echo "Batch payout created successfully!\n";
        echo "  ID: {$batchPayout->id}\n";
        echo "  Reference ID: {$batchPayout->referenceId}\n";
        echo "  Transactions: " . count($batchPayout->transactions) . "\n\n";
    } catch (APIException $error) {
        echo "Failed to create batch payout: {$error->getMessage()}\n\n";
    }

    echo "=== Checking account balance ===\n";

    try {
        $accountInfo = $payos->payoutsAccount->balance();
        echo "Account Balance:\n";
        echo "  Account Number: {$accountInfo->accountNumber}\n";
        echo "  Account Name: {$accountInfo->accountName}\n";
        echo "  Balance: {$accountInfo->balance} {$accountInfo->currency}\n";
    } catch (APIException $error) {
        echo "Failed to get balance: {$error->getMessage()}\n";
    }
} catch (Exception $error) {
    echo "Unexpected error: {$error->getMessage()}\n";
}

<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PayOS\Exceptions\APIException;
use PayOS\Models\V1\Payouts\GetPayoutListParam;
use PayOS\Models\V1\Payouts\PayoutApprovalState;
use PayOS\PayOS;

// Create a logger with console output
$logger = new Logger('payos');
$logger->pushHandler(new StreamHandler('php://stdout', Level::Info));

try {
    $payos = new PayOS(
        clientId: getenv('PAYOS_PAYOUT_CLIENT_ID') ?: null,
        apiKey: getenv('PAYOS_PAYOUT_API_KEY') ?: null,
        checksumKey: getenv('PAYOS_PAYOUT_CHECKSUM_KEY') ?: null,
        logger: $logger,
    );


    $params = new GetPayoutListParam(limit: 50, offset: 0, approvalState: PayoutApprovalState::COMPLETED);
    $page = $payos->payouts->list($params);

    $pagination = $page->getPagination();
    echo "Total payouts: {$pagination->total}\n";
    echo "Page 1 - Items: {$pagination->count}\n\n";

    // Show current page items
    foreach ($page->getData() as $index => $payout) {
        echo "  {$index}: {$payout->referenceId} - {$payout->approvalState->value}\n";
    }
    echo "===== Processing all items =====\n";
    // Processing throw all items
    foreach ($page->toArray() as $index => $payout) {
        echo "  {$index}: {$payout->referenceId} - {$payout->approvalState->value}\n";
    }
} catch (APIException $error) {
    echo "API Error: {$error->getMessage()}\n";
    if ($error->errorCode) {
        echo "Error Code: {$error->errorCode}\n";
    }
} catch (Exception $error) {
    echo "Error: {$error->getMessage()}\n";
}

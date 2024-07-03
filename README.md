# PayOS PHP library

[![Packagist Downloads](https://img.shields.io/packagist/dm/payos/payos)](https://packagist.org/packages/payos/payos)

PayOS is a PHP library for the PayOS API.

## Getting started

### Installation

```shell
composer require payos/payos
```

### Usage

You need to initialize the PayOS object with the Client ID, Api Key and Checksum Key of the payment channel you created, your Partner Code is optional.

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use PayOS\PayOS;

$payOS = new PayOS(CLIENT_ID, API_KEY, CHECKSUM_KEY);
// or
$payOS = new PayOS(CLIENT_ID, API_KEY, CHECKSUM_KEY, PARTNER_CODE);
```

### Create payment link

Create a payment link for the order data passed in the parameter.

Parameter data type:

```php
$data = [
    "orderCode" => "string",
    "amount" => "integer",
    "description" => "string",
    "returnUrl" => "string",
    "cancelUrl" => "string",
    "signature" => "string|null",
    "items" => "array|null",
    "buyerName" => "string|null",
    "buyerEmail" => "string|null",
    "buyerPhone" => "string|null",
    "buyerAddress" => "string|null",
    "expiredAt" => "integer|null",
];
```

Items data type:

```php
$items = [
    [
        "name" => "string",
        "quantity" => "integer",
        "price" => "integer",
    ]
];
```

Return data type:

```php
$response = [
    "bin" => "string",
    "accountNumber" => "string",
    "accountName" => "string",
    "amount" => "integer",
    "description" => "string",
    "orderCode" => "integer",
    "currency" => "string",
    "paymentLinkId" => "string",
    "status" => "string",
    "checkoutUrl" => "string",
    "qrCode" => "string",
];
```

Example:

```php
$data = [
    "orderCode" => intval(substr(strval(microtime(true) * 10000), -6)),
    "amount" => 2000,
    "description" => "Create payment link",
    "returnUrl" => $YOUR_DOMAIN . "/success.html",
    "cancelUrl" => $YOUR_DOMAIN . "/cancel.html"
];

try {
    $response = $payOS->createPaymentLink($data);
    return redirect($response['checkoutUrl']);
} catch (\Throwable $th) {
    return $th->getMessage();
}
```

### Get payment link information

Get payment information of an order that has created a payment link.

Parameter data type:

```php
$orderCode = "integer";
```

Return data type:

```php
$response = [
    "id" => "string",
    "orderCode" => "integer",
    "amount" => "integer",
    "amountPaid" => "integer",
    "amountRemaining" => "integer",
    "status" => "string",
    "createAt" => "string",
    "transactions" => "array",
    "cancellationReason" => "string|null",
    "canceledAt" => "string|null",
];
```

Transactions data type:

```php
$transactions = [
    [
        "reference" => "string",
        "amount" => "integer",
        "accountNumber" => "string",
        "description" => "string",
        "transactionDateTime" => "string",
        "virtualAccountName" => "string|null",
        "virtualAccountNumber" => "string|null",
        "counterAccountBankId" => "string|null",
        "counterAccountBankName" => "string|null",
        "counterAccountName" => "string|null",
        "counterAccountNumber" => "string|null",
    ]
];
```

Example:

```php
$orderCode = 123456;

try {
    $response = $payOS->getPaymentLinkInfomation($orderCode);
    return $response;
} catch (\Throwable $th) {
    return $th->getMessage();
}
```

### Cancel payment link

Cancel the payment link of the order.

Parameter data type:

```php
$orderCode = "integer";
$cancelBody = "string|null";
```

Return data type:

```php
$response = [
    "id" => "string",
    "orderCode" => "integer",
    "amount" => "integer",
    "amountPaid" => "integer",
    "amountRemaining" => "integer",
    "status" => "string",
    "createAt" => "string",
    "transactions" => "array",
    "cancellationReason" => "string|null",
    "canceledAt" => "string|null",
];
```

Transactions data type:

```php
$transactions = [
    [
        "reference" => "string",
        "amount" => "integer",
        "accountNumber" => "string",
        "description" => "string",
        "transactionDateTime" => "string",
        "virtualAccountName" => "string|null",
        "virtualAccountNumber" => "string|null",
        "counterAccountBankId" => "string|null",
        "counterAccountBankName" => "string|null",
        "counterAccountName" => "string|null",
        "counterAccountNumber" => "string|null",
    ]
];
```

Example:

```php
$orderCode = 123456;
$reason = "Cancel payment link";

try {
    $response = $payOS->cancelPaymentLink($orderCode, $cancelBody);
    return $response;
} catch (\Throwable $th) {
    return $th->getMessage();
}
```

### Confirm webhook

Validate the Webhook URL of a payment channel and add or update the Webhook URL for that Payment Channel if successful.

Example:

```php
$webhookUrl = "https://your-webhook-url/";

try {
    $payOS->confirmWebhook($webhookUrl);
    return "Webhook confirmed";
} catch (\Throwable $th) {
    return $th->getMessage();
}
```

### Verify webhook data

Verify data received via webhook after payment.

Parameter data type:

```php
$webhook = [
    "code" => "string",
    "desc" => "string",
    "data" => "array",
    "signature" => "string",
];

$data = [
    "orderCode" => "integer",
    "amount" => "integer",
    "description" => "string",
    "accountNumber" => "string",
    "reference" => "string",
    "transactionDateTime" => "string",
    "currency" => "string",
    "paymentLinkId" => "string",
    "code" => "string",
    "desc" => "string",
    "counterAccountBankId" => "string",
    "counterAccountBankName" => "string",
    "counterAccountName" => "string",
    "counterAccountNumber" => "string",
    "virtualAccountName" => "string",
    "virtualAccountNumber" => "string",
];
```

Return data type:

```php
$response = [
    "orderCode" => "integer",
    "amount" => "integer",
    "description" => "string",
    "accountNumber" => "string",
    "reference" => "string",
    "transactionDateTime" => "string",
    "currency" => "string",
    "paymentLinkId" => "string",
    "code" => "string",
    "desc" => "string",
    "counterAccountBankId" => "string",
    "counterAccountBankName" => "string",
    "counterAccountName" => "string",
    "counterAccountNumber" => "string",
    "virtualAccountName" => "string",
    "virtualAccountNumber" => "string",
];
```

Example:

```php
$webhook = $request->body();

try {
    $response = $payOS->verifyWebhookData($webhook);
    return $response;
} catch (\Throwable $th) {
    return $th->getMessage();
}
```

### Run test
```
vendor/bin/phpunit tests
```
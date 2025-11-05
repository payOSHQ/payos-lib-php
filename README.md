# payOS PHP Library

[![Packagist Downloads](https://img.shields.io/packagist/dm/payos/payos)](https://packagist.org/packages/payos/payos)
[![Packagist Version](https://img.shields.io/packagist/v/payos/payos)](https://packagist.org/packages/payos/payos)

The payOS PHP library provides convenient access to the payOS Merchant API from applications written in PHP.

To learn how to use the payOS Merchant API, check out our [API Reference](https://payos.vn/docs/api) and [Documentation](https://payos.vn/docs). We also have useful examples in the [`examples/`](./examples/) directory.

## Requirements

PHP 8.2 or higher.

## Installation

Install with Composer:

```bash
composer require payos/payos
```

Ensure that the `php-http/discovery` composer plugin is allowed to run or install a client manually if your project does not already have a PSR-18 HTTP client integrated.

```bash
composer require guzzlehttp/guzzle
```

## Usage

### Basic usage

First you need to initialize the client to interact with the payOS Merchant API.

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use PayOS\PayOS;

$payOS = new PayOS(
    clientId: getenv('PAYOS_CLIENT_ID'),
    apiKey: getenv('PAYOS_API_KEY'),
    checksumKey: getenv('PAYOS_CHECKSUM_KEY'),
);
```

After initialization you can call the client APIs using the resource objects. For example, to create a payment link use the `PaymentRequests` resource and the `CreatePaymentLinkRequest` model:

```php
<?php
use PayOS\Models\V2\PaymentRequests\CreatePaymentLinkRequest;

$paymentData = new CreatePaymentLinkRequest(
    orderCode: time(),
    amount: 2000,
    description: 'test payment',
    returnUrl: $YOUR_DOMAIN . '/success.html',
    cancelUrl: $YOUR_DOMAIN . '/cancel.html'
);

try {
    $result = $payOS->paymentRequests->create($paymentData);
    // $result is a CreatePaymentLinkResponse object
    // Use $result->checkoutUrl to get the redirect URL
} catch (\PayOS\Exceptions\APIException $e) {
    echo "API Error: " . $e->getMessage();
}
```

If you want to use the old array return values, add $options['asArray'] = true to the method call.

```php
$result = $payOS->paymentRequests->create($paymentData, options: ['asArray' => true]);
```

### Webhook verification

You can register an endpoint to receive payment webhooks and verify incoming payloads using the `Webhooks` resource:

```php
// Confirm/register a webhook URL (PayOS will validate the URL)
$confirmResult = $payOS->webhooks->confirm('https://your-webhook-url/');

// Verify a webhook payload received from PayOS
$webhookPayload = $request->getParsedBody(); // from PSR-7 request
try {
    $verified = $payOS->webhooks->verify($webhookPayload);
    // $verified is a WebhookData object
} catch (\PayOS\Exceptions\WebhookException $e) {
    echo 'Invalid webhook: ' . $e->getMessage();
}
```

For more information about webhooks, see [the API docs](https://payos.vn/docs/api/#tag/payment-webhook/operation/payment-webhook).

### Handling errors

When the API returns a non-success HTTP status (4xx/5xx) or a non-success code in the response payload (any code other than '00'), an `APIException` (or a subclass) will be thrown. Catch it to inspect status, headers and the API error payload.

```php
try {
    // example of calling a resource
    $page = $payOS->payouts->list();
} catch (\PayOS\Exceptions\APIException $e) {
    echo "API Error: " . $e->getMessage();
}
```

### Auto pagination

List endpoints are paginated. Use provided helpers to iterate through all pages or request pages manually (see [`example`](./examples/pagination_iteration.php)).

## Contributing

See [the contributing documentation](./CONTRIBUTING.md).

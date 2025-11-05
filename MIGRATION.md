# Migration Guide

This guide outlines the changes and steps needed to migrate your codebase from v1 to v2 of the payOS PHP SDK.

## Important Note

> [!WARNING]
> The minimum PHP version required for v2 is **PHP 8.2**. Please ensure your environment meets this requirement before proceeding with the migration. If you are using an older PHP version, you will need to upgrade your PHP installation first or continue using v1.

The PHP SDK v2 maintains **backward compatibility** by marking old methods as **deprecated** rather than removing them. This means:

- Your v1 code will continue to work after upgrading to v2
- You will receive deprecation warnings encouraging migration to new methods
- Old methods will be removed in a future major version (v3)
- We strongly recommend migrating to v2 methods for better features and support

## Breaking Changes

### Initialize Client

The library now uses [PSR-18 HTTP Client](https://www.php-fig.org/psr/psr-18/) instead of cURL directly, providing better flexibility and testability. The initialization remains similar but now supports more configuration options.

```php
use PayOS\PayOS;

$payos = new PayOS($clientId, $apiKey, $checksumKey, $partnerCode);

# Now the intialization can also accept more parameters:
$payos = new PayOS(
    clientId: $clientId,
    apiKey: $apiKey,
    checksumKey: $checksumKey,
    partnerCode: $partnerCode,
    baseURL: 'https://api-merchant.payos.vn',  // optional
    maxRetries: 2,  // optional
    logger: $customLogger,  // optional PSR-3 logger
    httpClient: $customHttpClient  // optional PSR-18 client
);
```

### Methods Name

All methods related to payment requests are now under `$payos->paymentRequests`.

```php
// before (v1 - still works but deprecated)
$result = $payos->createPaymentLink($paymentData);
$paymentInfo = $payos->getPaymentLinkInformation($orderCode);
$cancelled = $payos->cancelPaymentLink($orderCode, $cancellationReason);

// after (v2 - recommended)
$result = $payos->paymentRequests->create($paymentData);
$paymentInfo = $payos->paymentRequests->get($orderCode);
$cancelled = $payos->paymentRequests->cancel($orderCode, $cancellationReason);
```

For webhook-related methods, they are now under `$payos->webhooks`.

```php
// before (v1 - still works but deprecated)
$confirmed = $payos->confirmWebhook($webhookUrl);
$webhookData = $payos->verifyPaymentWebhookData($webhookBody);

// after (v2 - recommended)
$confirmed = $payos->webhooks->confirm($webhookUrl);
$webhookData = $payos->webhooks->verify($webhookBody);
```

### Types

Some types have been renamed and moved to proper namespaces for better organization. V2 methods support both typed objects and arrays for maximum flexibility.

```php
// before (v1 - using arrays)
$paymentData = [
    'orderCode' => time(),
    'amount' => 10000,
    'description' => 'Payment description',
    'returnUrl' => 'https://your-url.com/success',
    'cancelUrl' => 'https://your-url.com/cancel'
];
$result = $payos->createPaymentLink($paymentData);

// after (v2 - option 1: using typed classes, returns typed object)
use PayOS\Models\V2\PaymentRequests\CreatePaymentLinkRequest;
use PayOS\Models\V2\PaymentRequests\CreatePaymentLinkResponse;

$paymentData = new CreatePaymentLinkRequest(
    orderCode: time(),
    amount: 10000,
    description: 'Payment description',
    returnUrl: 'https://your-url.com/success',
    cancelUrl: 'https://your-url.com/cancel'
);
$result = $payos->paymentRequests->create($paymentData);
// $result is a CreatePaymentLinkResponse object

// after (v2 - option 2: using arrays, returns array)
$paymentData = [
    'orderCode' => time(),
    'amount' => 10000,
    'description' => 'Payment description',
    'returnUrl' => 'https://your-url.com/success',
    'cancelUrl' => 'https://your-url.com/cancel'
];
$result = $payos->paymentRequests->create($paymentData, ['asArray' => true]);
// $result is an array (similar to v1 behavior)
```

```php
// before (v1 - returns array)
$paymentInfo = $payos->getPaymentLinkInformation($orderCode);
$cancelled = $payos->cancelPaymentLink($orderCode);

// after (v2 - option 1: returns typed objects by default)
use PayOS\Models\V2\PaymentRequests\PaymentLink;

$paymentInfo = $payos->paymentRequests->get($orderCode);
// $paymentInfo is a PaymentLink object

$cancelled = $payos->paymentRequests->cancel($orderCode);
// $cancelled is a PaymentLink object

// after (v2 - option 2: returns arrays when specified)
$paymentInfo = $payos->paymentRequests->get($orderCode, ['asArray' => true]);
// $paymentInfo is an array (similar to v1 behavior)

$cancelled = $payos->paymentRequests->cancel($orderCode, null, ['asArray' => true]);
// $cancelled is an array (similar to v1 behavior)
```

```php
// before (v1 - returns string and array)
$confirmResult = $payos->confirmWebhook($webhookUrl);
// returns string: webhook URL

$webhookData = $payos->verifyPaymentWebhookData($webhookBody);
// returns array

// after (v2 - option 1: returns typed objects by default)
use PayOS\Models\Webhooks\ConfirmWebhookResponse;
use PayOS\Models\Webhooks\Webhook;
use PayOS\Models\Webhooks\WebhookData;

$confirmResult = $payos->webhooks->confirm($webhookUrl);
// $confirmResult is a ConfirmWebhookResponse object

$webhook = Webhook::fromArray($webhookBody);  // or pass array directly
$webhookData = $payos->webhooks->verify($webhook);
// $webhookData is a WebhookData object

// after (v2 - option 2: returns arrays when specified)
$confirmResult = $payos->webhooks->confirm($webhookUrl, ['asArray' => true]);
// $confirmResult is an array (similar to v1 behavior)

$webhookData = $payos->webhooks->verify($webhookBody, ['asArray' => true]);
// $webhookData is an array (similar to v1 behavior)
```

### Handling Errors

The library now throws specific exception types instead of generic `Exception`, providing better error handling capabilities.

```php
// before (v1 - generic Exception)
use Exception;

try {
    $result = $payos->createPaymentLink($paymentData);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    echo "Code: " . $e->getCode();
}

// after (v2 - specific exceptions)
use PayOS\Exceptions\APIException;
use PayOS\Exceptions\BadRequestException;
use PayOS\Exceptions\UnauthorizedException;
use PayOS\Exceptions\ConnectionException;

try {
    $result = $payos->paymentRequests->create($paymentData);
} catch (BadRequestException $e) {
    // Handle 400 errors
    echo "Bad Request: " . $e->getMessage();
    echo "Status: " . $e->status;
    echo "Error Code: " . $e->errorCode;
    echo "Error Description: " . $e->errorDesc;
} catch (UnauthorizedException $e) {
    // Handle 401 errors
    echo "Unauthorized: " . $e->getMessage();
} catch (APIException $e) {
    // Handle other API errors
    echo "API Error: " . $e->getMessage();
    echo "Status: " . $e->status;
    echo "Error Code: " . $e->errorCode;
} catch (ConnectionException $e) {
    // Handle connection errors
    echo "Connection Error: " . $e->getMessage();
}
```

Webhook-specific errors:

```php
// before (v1)
use Exception;

try {
    $webhookData = $payos->verifyPaymentWebhookData($webhookBody);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// after (v2)
use PayOS\Exceptions\WebhookException;

try {
    $webhookData = $payos->webhooks->verify($webhook);
} catch (WebhookException $e) {
    echo "Webhook Error: " . $e->getMessage();
}
```

## New Features in v2

### PSR Standards Support

V2 SDK now follows PHP-FIG standards:

- **PSR-3** Logger Interface - Integrate your own logging
- **PSR-18** HTTP Client - Use any HTTP client you prefer
- **PSR-7** HTTP Messages - Standard HTTP message interfaces

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use GuzzleHttp\Client;
use PayOS\PayOS;

$logger = new Logger('payos');
$logger->pushHandler(new StreamHandler('path/to/payos.log', Logger::DEBUG));

$httpClient = new Client(['timeout' => 30]);

$payos = new PayOS(
    clientId: $clientId,
    apiKey: $apiKey,
    checksumKey: $checksumKey,
    logger: $logger,
    httpClient: $httpClient
);
```

### Automatic Retry with Exponential Backoff

The SDK now automatically retries failed requests with exponential backoff for:

- Network errors
- 408 Request Timeout
- 429 Too Many Requests
- 5xx Server Errors

```php
use PayOS\PayOS;

$payos = new PayOS(
    clientId: $clientId,
    apiKey: $apiKey,
    checksumKey: $checksumKey,
    maxRetries: 3  // Configure retry attempts
);
```

### Return Types Flexibility

Methods now support returning either typed objects or arrays, making migration easier:

```php
// Return typed object (default, recommended for better IDE support)
$result = $payos->paymentRequests->create($paymentData);
// $result is CreatePaymentLinkResponse object
echo $result->checkoutUrl;  // IDE autocomplete supported

// Return array (useful for gradual migration from v1)
$result = $payos->paymentRequests->create($paymentData, ['asArray' => true]);
// $result is array (same format as v1)
echo $result['checkoutUrl'];

// You can also pass arrays as input with typed output
$result = $payos->paymentRequests->create([
    'orderCode' => time(),
    'amount' => 10000,
    'description' => 'test',
    'returnUrl' => 'https://example.com/success',
    'cancelUrl' => 'https://example.com/cancel'
]);
// $result is still CreatePaymentLinkResponse object
```

This flexibility means you can:

- **Quick migration**: Just update method calls, keep using arrays by adding `['asArray' => true]`
- **Gradual migration**: Start with arrays, then progressively adopt typed objects
- **Best of both worlds**: Mix and match based on your needs

### Enhanced Logging

Detailed request/response logging with sensitive data masking:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('payos');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

$payos = new PayOS(
    clientId: $clientId,
    apiKey: $apiKey,
    checksumKey: $checksumKey,
    logger: $logger
);

// All API calls will now be logged with details
$result = $payos->paymentRequests->create($paymentData);
```

## Migration Strategies

### Strategy 1: Quick Migration (Minimal Changes)

Keep using arrays with the new method structure:

```php
// Just update method calls and add ['asArray' => true]
$result = $payos->paymentRequests->create($paymentData, ['asArray' => true]);
$info = $payos->paymentRequests->get($orderCode, ['asArray' => true]);
```

### Strategy 2: Gradual Migration (Recommended)

Start with arrays, progressively adopt typed objects:

```php
// Phase 1: Use new methods with arrays
$result = $payos->paymentRequests->create($paymentData, ['asArray' => true]);

// Phase 2: Adopt typed objects for new code
$request = new CreatePaymentLinkRequest(/* ... */);
$result = $payos->paymentRequests->create($request);

// Phase 3: Update old code when touching it
```

### Strategy 3: Full Migration (Best Long-term)

Adopt all v2 features at once:

```php
// Use typed classes throughout
$request = new CreatePaymentLinkRequest(/* ... */);
$result = $payos->paymentRequests->create($request);
// Handle specific exceptions
// Add logging, etc.
```

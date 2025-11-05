<?php

namespace PayOS;

use Exception;
use PayOS\Core\APIResponse;
use PayOS\Core\FileDownloadResponse;
use PayOS\Core\HTTPClient;
use PayOS\Core\HTTPMethod;
use PayOS\Core\RequestOptions;
use PayOS\Crypto\CryptoProvider;
use PayOS\Exceptions\APIException;
use PayOS\Exceptions\ConnectionException;
use PayOS\Exceptions\ConnectionTimeoutError;
use PayOS\Exceptions\ErrorCode;
use PayOS\Exceptions\ErrorMessage;
use PayOS\Exceptions\InvalidSignatureException;
use PayOS\Resources\V1\Payouts\Payouts;
use PayOS\Resources\V1\PayoutsAccount\PayoutsAccount;
use PayOS\Resources\V2\PaymentRequests\PaymentRequests;
use PayOS\Resources\Webhooks\Webhooks;
use PayOS\Utils\PayOSSignatureUtils;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Log\LoggerInterface;

const PAYOS_BASE_URL = 'https://api-merchant.payos.vn';
const VERSION = "2.0.0";

/**
 * payOS API Client
 */
class PayOS
{
    private string $clientId;
    private string $apiKey;
    private string $checksumKey;
    private ?string $partnerCode;
    private string $baseURL;
    private LoggerInterface $logger;
    private int $maxRetries;
    private HTTPClient $httpClient;
    private CryptoProvider $crypto;

    /**
     * Payment Requests API resource
     *
     * @var PaymentRequests
     */
    public PaymentRequests $paymentRequests;

    /**
     * Webhooks API resource
     *
     * @var Webhooks
     */
    public Webhooks $webhooks;

    /**
     * Payouts Account API resource
     *
     * @var PayoutsAccount
     */
    public PayoutsAccount $payoutsAccount;

    /**
     * Payouts API resource
     *
     * @var Payouts
     */
    public Payouts $payouts;

    public function __construct(
        ?string $clientId = null,
        ?string $apiKey = null,
        ?string $checksumKey = null,
        ?string $partnerCode = null,
        ?string $baseURL = null,
        ?LoggerInterface $logger = null,
        ?int $maxRetries = null,
        ?HTTPClient $httpClient = null
    ) {
        $options = new PayOSOptions(
            $clientId,
            $apiKey,
            $checksumKey,
            $partnerCode,
            $baseURL,
            $maxRetries,
            $logger,
            $httpClient ? $httpClient->getClient() : null
        );
        $this->initializeFromOptions($options, $httpClient);

        // Initialize API resources
        $this->paymentRequests = new PaymentRequests($this);
        $this->webhooks = new Webhooks($this);
        $this->payoutsAccount = new PayoutsAccount($this);
        $this->payouts = new Payouts($this);
    }

    /**
     * Create payOS instance from PayOSOptions object
     */
    public static function options(PayOSOptions $options): self
    {
        $instance = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $instance->initializeFromOptions($options, null);

        // Initialize API resources
        $instance->paymentRequests = new PaymentRequests($instance);
        $instance->webhooks = new Webhooks($instance);
        $instance->payoutsAccount = new PayoutsAccount($instance);
        $instance->payouts = new Payouts($instance);

        return $instance;
    }

    private function initializeFromOptions(PayOSOptions $options, ?HTTPClient $httpClient = null): void
    {
        $this->clientId = $options->clientId;
        $this->apiKey = $options->apiKey;
        $this->checksumKey = $options->checksumKey;
        $this->partnerCode = $options->partnerCode;
        $this->baseURL = $options->baseURL;
        $this->logger = $options->logger;
        $this->maxRetries = $options->maxRetries;

        // Use provided httpClient or create new one from options
        if ($httpClient !== null) {
            $this->httpClient = $httpClient;
        } else {
            $this->httpClient = new HTTPClient(
                $options->httpClient,
                $options->requestFactory,
                $options->streamFactory
            );
        }

        $this->crypto = new CryptoProvider();
    }

    /**
     * Get user agent string
     */
    private function getUserAgent(): string
    {
        return 'PayOS/PHP ' . VERSION;
    }

    /**
     * Build headers array for PSR-7 request
     */
    protected function buildHeadersArray(?array $additionalHeaders = null): array
    {
        $headers = [
            'x-client-id' => $this->clientId,
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'User-Agent' => $this->getUserAgent(),
        ];

        if ($this->partnerCode) {
            $headers['x-partner-code'] = $this->partnerCode;
        }

        if ($additionalHeaders) {
            $headers = array_merge($headers, $additionalHeaders);
        }

        return $headers;
    }

    /**
     * Build URL with query parameters
     * @return non-empty-string
     */
    protected function buildUrl(string $endpoint, ?array $queries = null): string
    {
        $url = rtrim($this->baseURL, '/') . '/' . ltrim($endpoint, '/');

        if ($queries !== null && $queries !== []) {
            $params = [];
            foreach ($queries as $key => $val) {
                if ($val === null) {
                    continue;
                }

                if (is_array($val) || is_object($val)) {
                    $params[$key] = json_encode($val);
                } else {
                    $params[$key] = (string) $val;
                }
            }

            if ($params !== []) {
                $url .= '?' . http_build_query($params);
            }
        }

        return $url;
    }

    /**
     * Build request body
     */
    protected function buildBody(mixed $body): ?string
    {
        if ($body === null) {
            return null;
        }

        if (is_string($body)) {
            return $body;
        }

        $body_json = json_encode($body);

        return !$body_json ? null : $body_json;
    }

    /**
     * Check if request should be retried
     */
    private function shouldRetryRequest(int $statusCode): bool
    {
        if ($statusCode === 408) {
            return true;
        }
        if ($statusCode === 429) {
            return true;
        }
        if ($statusCode >= 500) {
            return true;
        }

        return false;
    }

    /**
     * Sleep for specified milliseconds
     */
    private function sleep(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }

    /**
     * Execute API request with retry logic using PSR-18
     *
     * @throws APIException
     */
    private function executeRequest(
        RequestOptions $options,
        ?int $retriesRemaining = null,
        ?string $retryOfRequestLogID = null
    ): mixed {
        if ($retriesRemaining === null) {
            $retriesRemaining = $options->maxRetries ?? $this->maxRetries;
        }

        $url = $this->buildUrl($options->path, $options->query);
        $body = $options->body;
        $requestHeaders = $options->headers;

        // Handle request signing if required
        if (isset($options->signatureOpts['request']) && $body) {
            $signature = null;

            switch ($options->signatureOpts['request']) {
                case 'create-payment-link':
                    $signature = $this->crypto->createSignatureOfPaymentRequest($body, $this->checksumKey);
                    if (!$signature) {
                        throw new InvalidSignatureException('Failed to create payment signature');
                    }
                    $body = array_merge($body, ['signature' => $signature]);

                    break;

                case 'body':
                    $signature = $this->crypto->createSignatureFromObj($body, $this->checksumKey);
                    if (!$signature) {
                        throw new InvalidSignatureException('Failed to create body signature');
                    }
                    $body = array_merge($body, ['signature' => $signature]);

                    break;

                case 'header':
                    $signature = $this->crypto->createSignature($this->checksumKey, $body);
                    $requestHeaders = $requestHeaders ? array_merge($requestHeaders, ['x-signature' => $signature]) : ['x-signature' => $signature];

                    break;

                default:
                    throw new InvalidSignatureException('Invalid signature request type');
            }
        }

        $headers = $this->buildHeadersArray($requestHeaders);
        $requestLogID = 'log_' . str_pad(dechex(rand(0, 16777215)), 6, '0', STR_PAD_LEFT);
        $retryLogStr = $retryOfRequestLogID ? ', retry of: ' . $retryOfRequestLogID : '';
        $startTime = microtime(true);

        $serializedBody = $this->buildBody($body);

        // Log request details at DEBUG level
        $this->logger->debug(
            "[{$requestLogID}{$retryLogStr}] sending request",
            [
                'url' => $url,
                'method' => $options->method->value,
                'headers' => $this->maskSensitiveHeaders($headers),
                'body' => $serializedBody,
                'retryOf' => $retryOfRequestLogID,
            ]
        );

        try {
            // Create PSR-7 request
            $request = $this->httpClient->createRequest($options->method->value, $url);

            // Add headers
            foreach ($headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }

            // Add body for non-GET requests
            if ($body && $options->method !== HTTPMethod::GET && $serializedBody !== null && $serializedBody !== '') {
                $stream = $this->httpClient->createStream($serializedBody);
                $request = $request->withBody($stream);
            }

            // Send request
            $response = $this->httpClient->sendRequest($request);
            $headersTime = microtime(true);
            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();
            $parsedHeaders = $this->parsePsr7Headers($response->getHeaders());
        } catch (NetworkExceptionInterface $e) {
            // Network/connection errors
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($retriesRemaining > 0) {
                $retryMessage = "retrying, {$retriesRemaining} attempts remaining";
                $this->logger->info("[{$requestLogID}] connection failed - {$retryMessage}");
                $this->logger->debug(
                    "[{$requestLogID}] connection failed - {$retryMessage}",
                    [
                        'url' => $url,
                        'message' => $e->getMessage(),
                        'durationMs' => $durationMs,
                        'retryOf' => $retryOfRequestLogID,
                    ]
                );

                return $this->retryRequest($options, $retriesRemaining, $retryOfRequestLogID ?? $requestLogID);
            }

            $this->logger->info("[{$requestLogID}] connection failed - error; no more retries left.");
            $this->logger->debug(
                "[{$requestLogID}] connection failed - error; no more retries left.",
                [
                    'url' => $url,
                    'message' => $e->getMessage(),
                    'durationMs' => $durationMs,
                    'retryOf' => $retryOfRequestLogID,
                ]
            );

            // Check if it's a timeout by examining the message
            if (str_contains($e->getMessage(), 'timeout') || str_contains($e->getMessage(), 'timed out')) {
                throw new ConnectionTimeoutError($e->getMessage());
            }

            throw new ConnectionException($e->getMessage());
        } catch (RequestExceptionInterface | ClientExceptionInterface $e) {
            // Other client errors
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logger->error(
                "[{$requestLogID}] request error",
                [
                    'url' => $url,
                    'message' => $e->getMessage(),
                    'durationMs' => $durationMs,
                ]
            );

            throw new ConnectionException($e->getMessage());
        }

        // Handle non-OK responses
        if ($statusCode < 200 || $statusCode >= 300) {
            $shouldRetry = $this->shouldRetryRequest($statusCode);
            $durationMs = (int) (($headersTime - $startTime) * 1000);
            $responseInfo = "[{$requestLogID}{$retryLogStr}] {$options->method->value} {$url} failed with status {$statusCode} in {$durationMs}ms";

            if ($retriesRemaining > 0 && $shouldRetry) {
                $retryMsg = "retrying, {$retriesRemaining} attempts remaining";
                $this->logger->info("{$responseInfo} - {$retryMsg}");
                $this->logger->debug(
                    "[{$requestLogID}] response error ({$retryMsg})",
                    [
                        'url' => $url,
                        'status' => $statusCode,
                        'headers' => $parsedHeaders,
                        'durationMs' => $durationMs,
                        'retryOf' => $retryOfRequestLogID,
                    ]
                );

                return $this->retryRequest(
                    $options,
                    $retriesRemaining,
                    $retryOfRequestLogID ?? $requestLogID,
                    $parsedHeaders
                );
            }

            $retryMessage = $shouldRetry ? 'error; no more retries left' : 'error: cannot retry';
            $this->logger->info("{$responseInfo} - {$retryMessage}");
            $errJSON = json_decode($responseBody, true);
            $errMessage = $errJSON ? null : $responseBody;
            $this->logger->debug(
                "[{$requestLogID}] response error ({$retryMessage})",
                [
                    'url' => $url,
                    'status' => $statusCode,
                    'headers' => $parsedHeaders,
                    'message' => $errMessage,
                    'durationMs' => $durationMs,
                    'retryOf' => $retryOfRequestLogID,
                ]
            );
            $err = APIException::generateException($statusCode, $errJSON, $errMessage, $parsedHeaders);

            throw $err;
        }

        // Log successful response
        $durationMs = (int) (($headersTime - $startTime) * 1000);
        $this->logger->info(
            "[{$requestLogID}{$retryLogStr}] {$options->method->value} {$url} succeeded with status {$statusCode} in {$durationMs}ms"
        );

        // Parse successful response
        $rawJson = json_decode($responseBody, true);
        if (!$rawJson) {
            throw new APIException($statusCode, null, 'Invalid JSON response', $parsedHeaders);
        }

        $apiResponse = APIResponse::fromArray($rawJson);

        if ($apiResponse->code !== '00' || !$apiResponse->data) {
            throw APIException::generateException(
                $statusCode,
                ['data' => $apiResponse->data, 'code' => $apiResponse->code, 'desc' => $apiResponse->desc, 'signature' => $apiResponse->signature],
                $apiResponse->desc,
                $parsedHeaders
            );
        }

        // Verify response signature if required
        // @phpstan-ignore-next-line notIdentical.alwaysTrue
        if (isset($options->signatureOpts['response']) && $apiResponse->data !== null) {
            $resSignature = $options->signatureOpts['response'] === 'body' ?
                $apiResponse->signature : ($parsedHeaders['x-signature'] ?? null);

            if ($resSignature !== null) {
                $signedSignature = $options->signatureOpts['response'] === 'body' ?
                    $this->crypto->createSignatureFromObj($apiResponse->data, $this->checksumKey) :
                    $this->crypto->createSignature($this->checksumKey, $apiResponse->data);

                if ($resSignature !== $signedSignature) {
                    throw new InvalidSignatureException('Data integrity check failed');
                }
            }
        }

        return $apiResponse->data;
    }

    /**
     * Retry request with exponential backoff
     */
    private function retryRequest(
        RequestOptions $options,
        int $retriesRemaining,
        string $retryOfRequestLogID,
        ?array $responseHeaders = null
    ): mixed {
        $timeoutMs = null;
        $retryAfter = $responseHeaders['retry-after'] ?? null;
        $rateLimitReset = $responseHeaders['x-ratelimit-reset'] ?? null;

        // Parse Retry-After header
        if ($retryAfter) {
            $timeoutSecond = floatval($retryAfter);
            if (!is_nan($timeoutSecond)) {
                $timeoutMs = (int) ($timeoutSecond * 1000);
            } else {
                $timeoutMs = strtotime($retryAfter) * 1000 - time() * 1000;
            }
        }

        if ($rateLimitReset) {
            $timeoutSecond = floatval($rateLimitReset);
            if (!is_nan($timeoutSecond)) {
                $timeoutMs = (int) ($timeoutSecond * 1000) - (time() * 1000);
            }
        }

        // Apply exponential backoff if no retry header
        if (!($timeoutMs && $timeoutMs >= 0 && $timeoutMs < 60000)) {
            $initRetryDelay = 0.5;
            $maxRetryDelay = 10.0;
            $numRetries = ($options->maxRetries ?? $this->maxRetries) - $retriesRemaining;
            $sleepSeconds = min($initRetryDelay * pow(2, $numRetries), $maxRetryDelay);
            // Apply jitter to avoid thundering herd
            $jitter = 1 - (mt_rand() / mt_getrandmax()) * 0.25;
            $timeoutMs = (int) ($sleepSeconds * $jitter * 1000);
        }

        $this->sleep($timeoutMs);

        return $this->executeRequest($options, $retriesRemaining - 1, $retryOfRequestLogID);
    }

    /**
     * Format headers array for logging (hides sensitive data)
     */
    private function maskSensitiveHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $key => $value) {
            // Mask sensitive headers
            if (in_array(strtolower($key), ['x-api-key', 'x-signature'])) {
                $value = '***REDACTED***';
            }
            $formatted[$key] = $value;
        }

        return $formatted;
    }

    /**
     * Parse PSR-7 response headers to associative array
     */
    private function parsePsr7Headers(array $headers): array
    {
        $parsed = [];
        foreach ($headers as $name => $values) {
            $parsed[strtolower($name)] = is_array($values) ? implode(', ', $values) : $values;
        }

        return $parsed;
    }

    /**
     * Execute file download request using PSR-18
     *
     * @throws APIException
     */
    private function executeFileDownload(
        RequestOptions $options,
        ?int $retriesRemaining = null,
        ?string $retryOfRequestLogID = null
    ): FileDownloadResponse {
        if ($retriesRemaining === null) {
            $retriesRemaining = $options->maxRetries ?? $this->maxRetries;
        }

        $url = $this->buildUrl($options->path, $options->query);
        $headers = $this->buildHeadersArray($options->headers);
        $requestLogID = $retryOfRequestLogID ?? 'log_' . bin2hex(random_bytes(3));
        $retryLogStr = $retryOfRequestLogID ? ', retry of: ' . $retryOfRequestLogID : '';
        $startTime = microtime(true);

        // Log file download request at DEBUG level
        $this->logger->debug(
            "[{$requestLogID}{$retryLogStr}] sending file download request",
            [
                'url' => $url,
                'method' => $options->method->value,
                'headers' => $this->maskSensitiveHeaders($headers),
                'retryOf' => $retryOfRequestLogID,
            ]
        );

        try {
            // Create PSR-7 request
            $request = $this->httpClient->createRequest($options->method->value, $url);

            // Add headers
            foreach ($headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }

            // Send request
            $response = $this->httpClient->sendRequest($request);
            $headersTime = microtime(true);
            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();
            $parsedHeaders = $this->parsePsr7Headers($response->getHeaders());
        } catch (NetworkExceptionInterface $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($retriesRemaining > 0) {
                $retryMessage = "retrying, {$retriesRemaining} attempts remaining";
                $this->logger->info("[{$requestLogID}] connection failed - {$retryMessage}");
                $this->logger->debug(
                    "[{$requestLogID}] connection failed - {$retryMessage}",
                    [
                        'url' => $url,
                        'message' => $e->getMessage(),
                        'durationMs' => $durationMs,
                        'retryOf' => $retryOfRequestLogID,
                    ]
                );

                return $this->retryDownloadFile($options, $retriesRemaining, $requestLogID);
            }

            $this->logger->info("[{$requestLogID}] connection failed - error; no more retries left.");
            $this->logger->debug(
                "[{$requestLogID}] connection failed - error; no more retries left.",
                [
                    'url' => $url,
                    'message' => $e->getMessage(),
                    'durationMs' => $durationMs,
                    'retryOf' => $retryOfRequestLogID,
                ]
            );

            if (str_contains($e->getMessage(), 'timeout') || str_contains($e->getMessage(), 'timed out')) {
                throw new ConnectionTimeoutError($e->getMessage());
            }

            throw new ConnectionException($e->getMessage());
        } catch (RequestExceptionInterface | ClientExceptionInterface $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $this->logger->error(
                "[{$requestLogID}] request error",
                [
                    'url' => $url,
                    'message' => $e->getMessage(),
                    'durationMs' => $durationMs,
                ]
            );

            throw new ConnectionException($e->getMessage());
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $shouldRetry = $this->shouldRetryRequest($statusCode);
            $durationMs = (int) (($headersTime - $startTime) * 1000);
            $responseInfo = "[{$requestLogID}{$retryLogStr}] {$options->method->value} {$url} failed with status {$statusCode} in {$durationMs}ms";

            if ($retriesRemaining > 0 && $shouldRetry) {
                $retryMsg = "retrying, {$retriesRemaining} attempts remaining";
                $this->logger->info("{$responseInfo} - {$retryMsg}");
                $this->logger->debug(
                    "[{$requestLogID}] response error ({$retryMsg})",
                    [
                        'url' => $url,
                        'status' => $statusCode,
                        'headers' => $parsedHeaders,
                        'durationMs' => $durationMs,
                        'retryOf' => $retryOfRequestLogID,
                    ]
                );

                return $this->retryDownloadFile(
                    $options,
                    $retriesRemaining,
                    $requestLogID,
                    $parsedHeaders
                );
            }

            $retryMessage = $shouldRetry ? 'error; no more retries left' : 'error: cannot retry';
            $this->logger->info("{$responseInfo} - {$retryMessage}");
            $errJSON = json_decode($responseBody, true);
            $errMessage = $errJSON ? null : $responseBody;
            $this->logger->debug(
                "[{$requestLogID}] response error ({$retryMessage})",
                [
                    'url' => $url,
                    'status' => $statusCode,
                    'headers' => $parsedHeaders,
                    'message' => $errMessage,
                    'durationMs' => $durationMs,
                    'retryOf' => $retryOfRequestLogID,
                ]
            );

            throw APIException::generateException($statusCode, $errJSON, $errMessage, $parsedHeaders);
        }

        // Log successful file download
        $durationMs = (int) (($headersTime - $startTime) * 1000);
        $this->logger->info(
            "[{$requestLogID}{$retryLogStr}] {$options->method->value} {$url} succeeded with status {$statusCode} in {$durationMs}ms"
        );

        // Check if response is JSON (error response)
        $contentType = $parsedHeaders['content-type'] ?? 'application/octet-stream';
        if (strpos($contentType, 'application/json') !== false) {
            $rawJson = json_decode($responseBody, true);
            $apiResponse = APIResponse::fromArray($rawJson);

            throw APIException::generateException(
                $statusCode,
                ['code' => $apiResponse->code, 'desc' => $apiResponse->desc],
                $apiResponse->desc,
                $parsedHeaders
            );
        }

        $contentDisposition = $parsedHeaders['content-disposition'] ?? null;
        $contentLength = $parsedHeaders['content-length'] ?? null;

        $filename = null;
        if ($contentDisposition) {
            if (preg_match('/filename[^;=\n]*=(([\'"]).*?\2|[^;\n]*)/', $contentDisposition, $matches)) {
                $filename = trim($matches[1], '\'"');
            }
        }

        return new FileDownloadResponse(
            $filename,
            $contentType,
            $contentLength ? (int) $contentLength : null,
            $responseBody
        );
    }

    /**
     * Retry file download
     */
    private function retryDownloadFile(
        RequestOptions $options,
        int $retriesRemaining,
        string $retryOfRequestLogID,
        ?array $responseHeaders = null
    ): FileDownloadResponse {
        $timeoutMs = null;
        $retryAfter = $responseHeaders['retry-after'] ?? null;
        $rateLimitReset = $responseHeaders['x-ratelimit-reset'] ?? null;

        if ($retryAfter) {
            $timeoutSecond = floatval($retryAfter);
            if (!is_nan($timeoutSecond)) {
                $timeoutMs = (int) ($timeoutSecond * 1000);
            } else {
                $timeoutMs = strtotime($retryAfter) * 1000 - time() * 1000;
            }
        }

        if ($rateLimitReset) {
            $timeoutSecond = floatval($rateLimitReset);
            if (!is_nan($timeoutSecond)) {
                $timeoutMs = (int) ($timeoutSecond * 1000);
            }
        }

        if (!($timeoutMs && $timeoutMs >= 0 && $timeoutMs < 60000)) {
            $initRetryDelay = 0.5;
            $maxRetryDelay = 10.0;
            $numRetries = ($options->maxRetries ?? $this->maxRetries) - $retriesRemaining;
            $sleepSeconds = min($initRetryDelay * pow(2, $numRetries), $maxRetryDelay);
            $jitter = 1 - (mt_rand() / mt_getrandmax()) * 0.25;
            $timeoutMs = (int) ($sleepSeconds * $jitter * 1000);
        }

        $this->sleep($timeoutMs);

        return $this->executeFileDownload($options, $retriesRemaining - 1, $retryOfRequestLogID);
    }

    /**
     * Make GET request
     */
    public function get(string $path, ?array $options = null): mixed
    {
        return $this->request(HTTPMethod::GET, $path, $options);
    }

    /**
     * Make POST request
     */
    public function post(string $path, ?array $options = null): mixed
    {
        return $this->request(HTTPMethod::POST, $path, $options);
    }

    /**
     * Make PATCH request
     */
    public function patch(string $path, ?array $options = null): mixed
    {
        return $this->request(HTTPMethod::PATCH, $path, $options);
    }

    /**
     * Make PUT request
     */
    public function put(string $path, ?array $options = null): mixed
    {
        return $this->request(HTTPMethod::PUT, $path, $options);
    }

    /**
     * Make DELETE request
     */
    public function delete(string $path, ?array $options = null): mixed
    {
        return $this->request(HTTPMethod::DELETE, $path, $options);
    }

    /**
     * Make HTTP request
     *
     * @throws APIException
     */
    public function request(HTTPMethod $method, string $path, ?array $options = null): mixed
    {
        $requestOptions = new RequestOptions(
            method: $method,
            path: $path,
            query: $options['query'] ?? null,
            body: $options['body'] ?? null,
            headers: $options['headers'] ?? null,
            maxRetries: $options['maxRetries'] ?? null,
            signatureOpts: $options['signatureOpts'] ?? null
        );

        return $this->executeRequest($requestOptions, $options['remainingRetries'] ?? null);
    }

    /**
     * Download file
     *
     * @throws APIException
     */
    public function downloadFile(string $path, ?array $options = null): FileDownloadResponse
    {
        $requestOptions = new RequestOptions(
            $options['method'] ?? HTTPMethod::GET,
            $path,
            $options['query'] ?? null,
            $options['body'] ?? null,
            $options['headers'] ?? null,
            $options['maxRetries'] ?? null,
            null // No signature for file downloads
        );

        return $this->executeFileDownload($requestOptions, $options['remainingRetries'] ?? null);
    }

    /**
     * Get client ID
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * Get API key
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Get checksum key
     */
    public function getChecksumKey(): string
    {
        return $this->checksumKey;
    }

    /**
     * Get partner code
     */
    public function getPartnerCode(): ?string
    {
        return $this->partnerCode;
    }

    /**
     * Get base URL
     */
    public function getBaseURL(): string
    {
        return $this->baseURL;
    }

    /**
     * Get crypto provider
     */
    public function getCrypto(): CryptoProvider
    {
        return $this->crypto;
    }

    /** V1 IMPLEMENT, WILL BE REMOVE IN THE FUTURE */

    /**
     * Create a payment link for the order data passed in the parameter.
     *
     * @param  array $paymentData Payment data
     * @return array
     * @throws Exception
     * @deprecated Use $payos->paymentRequests->create() instead. This method will be removed in a future version.
     * @see \PayOS\Resources\V2\PaymentRequests\PaymentRequests::create()
     */
    public function createPaymentLink(array $paymentData): array
    {
        $orderCode = $paymentData['orderCode'] ?? null;
        $amount = $paymentData['amount'] ?? null;
        $returnUrl = $paymentData['returnUrl'] ?? null;
        $cancelUrl = $paymentData['cancelUrl'] ?? null;
        $description = $paymentData['description'] ?? null;

        if (!($paymentData && $orderCode && $amount && $returnUrl && $cancelUrl && $description)) {
            $requiredPaymentData = [
                'orderCode' => $orderCode,
                'amount' => $amount,
                'returnUrl' => $returnUrl,
                'cancelUrl' => $cancelUrl,
                'description' => $description,
            ];
            $requiredKeys = array_keys($requiredPaymentData);
            $keysError = array_filter($requiredKeys, function ($key) use ($requiredPaymentData) {
                return $requiredPaymentData[$key] === null;
            });

            $msgError = ErrorMessage::INVALID_PARAMETER . ' ' . implode(', ', $keysError) . ' must not be null.';

            throw new Exception($msgError, ErrorCode::INVALID_PARAMETER);
        }
        $url = PAYOS_BASE_URL . '/v2/payment-requests';
        $signaturePaymentRequest = PayOSSignatureUtils::createSignatureOfPaymentRequest(
            $this->checksumKey,
            $paymentData
        );

        try {
            $headers = [
                'x-client-id: ' . $this->clientId,
                'x-api-key: ' . $this->apiKey,
                'Content-Type: application/json',
            ];
            if ($this->partnerCode != null) {
                array_push($headers, 'x-partner-code: ' . $this->partnerCode);
            }
            $data = array_merge($paymentData, ['signature' => $signaturePaymentRequest]);

            $paymentRequest = curl_init();
            curl_setopt($paymentRequest, CURLOPT_URL, $url);
            curl_setopt($paymentRequest, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($paymentRequest, CURLOPT_HTTPHEADER, $headers);

            curl_setopt($paymentRequest, CURLOPT_POST, 1);
            curl_setopt($paymentRequest, CURLOPT_POSTFIELDS, json_encode($data));
            $paymentLinkRes = curl_exec($paymentRequest);

            #Ensure to close curl
            curl_close($paymentRequest);
            $paymentLinkRes = json_decode($paymentLinkRes, true);

            if ($paymentLinkRes['code'] == '00') {
                $paymentLinkResSignature = PayOSSignatureUtils::createSignatureFromObj(
                    $this->checksumKey,
                    $paymentLinkRes['data']
                );
                if ($paymentLinkResSignature !== $paymentLinkRes['signature']) {
                    throw new Exception(ErrorMessage::DATA_NOT_INTEGRITY, ErrorCode::DATA_NOT_INTEGRITY);
                }
                if ($paymentLinkRes['data']) {
                    return $paymentLinkRes['data'];
                }
            }

            throw new Exception($paymentLinkRes['desc'], $paymentLinkRes['code']);
        } catch (Exception $error) {
            throw new Exception($error->getMessage(), $error->getCode());
        }
    }

    /**
     * Get payment information of an order that has created a payment link.
     *
     * @param string|int $orderCode Order code
     * @return array
     * @throws Exception
     * @deprecated Use $payos->paymentRequests->get() instead. This method will be removed in a future version.
     * @see \PayOS\Resources\V2\PaymentRequests\PaymentRequests::get()
     */
    public function getPaymentLinkInformation(string|int $orderCode): array
    {
        if (!$orderCode || (is_string($orderCode) && strlen($orderCode) == 0) || (is_int($orderCode) && $orderCode < 0)) {
            throw new Exception(ErrorMessage::INVALID_PARAMETER, ErrorCode::INVALID_PARAMETER);
        }
        $url = PAYOS_BASE_URL . '/v2/payment-requests/' . $orderCode;

        try {
            $headers = [
                'x-client-id: ' . $this->clientId,
                'x-api-key: ' . $this->apiKey,
                'Content-Type: application/json',
            ];

            $paymentRequest = curl_init();
            curl_setopt($paymentRequest, CURLOPT_URL, $url);
            curl_setopt($paymentRequest, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($paymentRequest, CURLOPT_HTTPHEADER, $headers);

            $paymentLinkRes = curl_exec($paymentRequest);

            # Ensure to close curl
            curl_close($paymentRequest);
            $paymentLinkRes = json_decode($paymentLinkRes, true);

            if ($paymentLinkRes['code'] == '00') {
                $paymentLinkResSignature = PayOSSignatureUtils::createSignatureFromObj(
                    $this->checksumKey,
                    $paymentLinkRes['data']
                );
                if ($paymentLinkResSignature !== $paymentLinkRes['signature']) {
                    throw new Exception(ErrorMessage::DATA_NOT_INTEGRITY, ErrorCode::DATA_NOT_INTEGRITY);
                }
                if ($paymentLinkRes['data']) {
                    return $paymentLinkRes['data'];
                }
            }

            throw new Exception($paymentLinkRes['desc'], $paymentLinkRes['code']);
        } catch (Exception $error) {
            throw new Exception($error->getMessage(), $error->getCode());
        }
    }

    /**
     * Validate the Webhook URL of a payment channel and add or update the Webhook URL for that Payment Channel if successful.
     *
     * @param string $webhookUrl Webhook URL
     * @return string
     * @throws Exception
     * @deprecated Use $payos->webhooks->confirm() instead. This method will be removed in a future version.
     * @see \PayOS\Resources\Webhooks\Webhooks::confirm()
     */
    public function confirmWebhook(string $webhookUrl): string
    {
        if (!$webhookUrl || strlen($webhookUrl) == 0) {
            throw new Exception(ErrorMessage::INVALID_PARAMETER, ErrorCode::INVALID_PARAMETER);
        }
        $url = PAYOS_BASE_URL . '/confirm-webhook';

        try {
            $headers = [
                'x-client-id: ' . $this->clientId,
                'x-api-key: ' . $this->apiKey,
                'Content-Type: application/json',
            ];

            $data = [
                'webhookUrl' => $webhookUrl,
            ];

            $confirmWebhookRequest = curl_init();
            curl_setopt($confirmWebhookRequest, CURLOPT_URL, $url);
            curl_setopt($confirmWebhookRequest, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($confirmWebhookRequest, CURLOPT_HTTPHEADER, $headers);

            curl_setopt($confirmWebhookRequest, CURLOPT_POST, 1);
            curl_setopt($confirmWebhookRequest, CURLOPT_POSTFIELDS, json_encode($data));
            $confirmWebhookRes = curl_exec($confirmWebhookRequest);

            #Ensure to close curl
            curl_close($confirmWebhookRequest);
            $confirmWebhookRes = json_decode($confirmWebhookRes, true);
            $reponseCode = curl_getinfo($confirmWebhookRequest, CURLINFO_HTTP_CODE);

            if ($reponseCode == '400') {
                throw new Exception(ErrorMessage::WEBHOOK_URL_INVALID, ErrorCode::WEBHOOK_URL_INVALID);
            } elseif ($reponseCode == '401') {
                throw new Exception(ErrorMessage::UNAUTHORIZED, ErrorCode::UNAUTHORIZED);
            } elseif (str_starts_with($reponseCode, '5')) {
                throw new Exception(ErrorMessage::INTERNAL_SERVER_ERROR, ErrorCode::INTERNAL_SERVER_ERROR);
            }

            return $webhookUrl;
        } catch (Exception $error) {
            throw new Exception($error->getMessage(), $error->getCode());
        }
    }

    /**
     * Cancel the payment link of the order.
     *
     * @param string|int $orderCode Order code
     * @param ?string cancellationReason Reason for cancelling payment link (optional)
     * @return array
     * @throws Exception
     * @deprecated Use $payos->paymentRequests->cancel() instead. This method will be removed in a future version.
     * @see \PayOS\Resources\V2\PaymentRequests\PaymentRequests::cancel()
     */
    public function cancelPaymentLink(string|int $orderCode, ?string $cancellationReason = null): array
    {
        if (!$orderCode || (is_string($orderCode) && strlen($orderCode) == 0) || (is_int($orderCode) && $orderCode < 0)) {
            throw new Exception(ErrorMessage::INVALID_PARAMETER, ErrorCode::INVALID_PARAMETER);
        }
        $url = PAYOS_BASE_URL . '/v2/payment-requests/' . $orderCode . '/cancel';

        try {
            $headers = [
                'x-client-id: ' . $this->clientId,
                'x-api-key: ' . $this->apiKey,
                'Content-Type: application/json',
            ];
            $data = [
                'cancellationReason' => $cancellationReason,
            ];

            $cancelPaymentLinkRequest = curl_init();
            curl_setopt($cancelPaymentLinkRequest, CURLOPT_URL, $url);
            curl_setopt($cancelPaymentLinkRequest, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($cancelPaymentLinkRequest, CURLOPT_HTTPHEADER, $headers);

            curl_setopt($cancelPaymentLinkRequest, CURLOPT_POST, 1);
            curl_setopt($cancelPaymentLinkRequest, CURLOPT_POSTFIELDS, json_encode($data));
            $cancelPaymentLinkRes = curl_exec($cancelPaymentLinkRequest);

            #Ensure to close curl
            curl_close($cancelPaymentLinkRequest);
            $cancelPaymentLinkRes = json_decode($cancelPaymentLinkRes, true);

            if ($cancelPaymentLinkRes['code'] == '00') {
                $cancelPaymentLinkResSignature = PayOSSignatureUtils::createSignatureFromObj(
                    $this->checksumKey,
                    $cancelPaymentLinkRes['data']
                );
                if ($cancelPaymentLinkResSignature !== $cancelPaymentLinkRes['signature']) {
                    throw new Exception(ErrorMessage::DATA_NOT_INTEGRITY, ErrorCode::DATA_NOT_INTEGRITY);
                }
                if ($cancelPaymentLinkRes['data']) {
                    return $cancelPaymentLinkRes['data'];
                }
            }

            throw new Exception($cancelPaymentLinkRes['desc'], $cancelPaymentLinkRes['code']);
        } catch (Exception $error) {
            throw new Exception($error->getMessage(), $error->getCode());
        }
    }

    /**
     * Verify data received via webhook after payment.
     *
     * @param array $webhookBody Request body received from webhook
     * @return array
     * @throws Exception
     * @deprecated Use $payos->webhooks->verify() instead. This method will be removed in a future version.
     * @see \PayOS\Resources\Webhooks\Webhooks::verify()
     */
    public function verifyPaymentWebhookData(array $webhookBody): array
    {
        if (!$webhookBody || count($webhookBody) == 0) {
            throw new Exception(ErrorMessage::NO_DATA, ErrorCode::NO_DATA);
        }
        $signature = $webhookBody['signature'] ?? null;
        $data = $webhookBody['data'] ?? null;

        if (!$signature) {
            throw new Exception(ErrorMessage::NO_SIGNATURE, ErrorCode::NO_SIGNATURE);
        }
        if (!$data) {
            throw new Exception(ErrorMessage::NO_DATA, ErrorCode::NO_DATA);
        }
        $signatureData = PayOSSignatureUtils::createSignatureFromObj($this->checksumKey, $data);
        if ($signatureData !== $signature) {
            throw new Exception(ErrorMessage::DATA_NOT_INTEGRITY, ErrorCode::DATA_NOT_INTEGRITY);
        }

        return $data;
    }

    /** END V1 IMPLEMENT, WILL BE REMOTE IN THE FUTURE */
}

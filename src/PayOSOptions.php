<?php

namespace PayOS;

use PayOS\Exceptions\PayOSException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * payOS API Client configuration options
 */
class PayOSOptions
{
    public string $clientId;
    public string $apiKey;
    public string $checksumKey;
    public ?string $partnerCode;
    public string $baseURL;
    public int $maxRetries;
    public LoggerInterface $logger;
    public ?ClientInterface $httpClient;
    public ?RequestFactoryInterface $requestFactory;
    public ?StreamFactoryInterface $streamFactory;

    public const MAX_RETRIES = 2;

    public function __construct(
        ?string $clientId = null,
        ?string $apiKey = null,
        ?string $checksumKey = null,
        ?string $partnerCode = null,
        ?string $baseURL = null,
        ?int $maxRetries = null,
        ?LoggerInterface $logger = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ) {
        $this->clientId = $clientId ?? getenv('PAYOS_CLIENT_ID') ?: throw new PayOSException(
            'The PAYOS_CLIENT_ID environment variable is missing or empty; either provide it, or instantiate the PayOS with a clientId option.'
        );
        $this->apiKey = $apiKey ?? getenv('PAYOS_API_KEY') ?: throw new PayOSException(
            'The PAYOS_API_KEY environment variable is missing or empty; either provide it, or instantiate the PayOS with an apiKey option.'
        );
        $this->checksumKey = $checksumKey ?? getenv('PAYOS_CHECKSUM_KEY') ?: throw new PayOSException(
            'The PAYOS_CHECKSUM_KEY environment variable is missing or empty; either provide it, or instantiate the PayOS with a checksumKey option.'
        );
        $this->partnerCode = $partnerCode ?? getenv('PAYOS_PARTNER_CODE') ?: null;
        $this->baseURL = $baseURL ?? getenv('PAYOS_BASE_URL') ?: PAYOS_BASE_URL;
        $this->maxRetries = $maxRetries ?? self::MAX_RETRIES;
        $this->logger = $logger ?? new NullLogger();
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }
}

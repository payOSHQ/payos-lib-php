<?php

namespace PayOS\Core;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use PsrDiscovery\Discover;

/**
 * PSR-18 HTTP Client wrapper with automatic discovery
 */
class HTTPClient
{
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct(
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ) {
        $this->client = $client ?? $this->discoverClient();
        $this->requestFactory = $requestFactory ?? $this->discoverRequestFactory();
        $this->streamFactory = $streamFactory ?? $this->discoverStreamFactory();
    }

    /**
     * Discover PSR-18 HTTP Client
     */
    private function discoverClient(): ClientInterface
    {
        $client = Discover::httpClient();

        if ($client === null) {
            throw new \RuntimeException(
                'No PSR-18 HTTP Client implementation found. ' .
                    'Please install one of the following packages: ' .
                    'guzzlehttp/guzzle, symfony/http-client, php-http/curl-client'
            );
        }

        return $client;
    }

    /**
     * Discover PSR-17 Request Factory
     */
    private function discoverRequestFactory(): RequestFactoryInterface
    {
        $factory = Discover::httpRequestFactory();

        if ($factory === null) {
            throw new \RuntimeException(
                'No PSR-17 Request Factory implementation found. ' .
                    'Please install one of the following packages: ' .
                    'guzzlehttp/psr7, nyholm/psr7, laminas/laminas-diactoros'
            );
        }

        return $factory;
    }

    /**
     * Discover PSR-17 Stream Factory
     */
    private function discoverStreamFactory(): StreamFactoryInterface
    {
        $factory = Discover::httpStreamFactory();

        if ($factory === null) {
            throw new \RuntimeException(
                'No PSR-17 Stream Factory implementation found. ' .
                    'Please install one of the following packages: ' .
                    'guzzlehttp/psr7, nyholm/psr7, laminas/laminas-diactoros'
            );
        }

        return $factory;
    }

    /**
     * Create a PSR-7 request
     */
    public function createRequest(string $method, string $uri): RequestInterface
    {
        return $this->requestFactory->createRequest($method, $uri);
    }

    /**
     * Create a PSR-7 stream from string
     */
    public function createStream(string $content = ''): \Psr\Http\Message\StreamInterface
    {
        return $this->streamFactory->createStream($content);
    }

    /**
     * Send a PSR-7 request and get response
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client->sendRequest($request);
    }

    /**
     * Get the underlying PSR-18 client
     */
    public function getClient(): ClientInterface
    {
        return $this->client;
    }
}

<?php

// Mimics the Symfony 5.4 HttpClient/Psr18Client shape (no withOptions()) when symfony/http-client is absent.

namespace Symfony\Component\HttpClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class HttpClient
{
    /** @var array<string, mixed>|null */
    public static ?array $lastOptions = null;

    /**
     * @param array<string, mixed> $options
     */
    public static function create(array $options = []): \stdClass
    {
        self::$lastOptions = $options;
        $client = new \stdClass();
        $client->options = $options;

        return $client;
    }
}

final class Psr18Client implements ClientInterface
{
    public mixed $client;

    public function __construct(mixed $client = null, mixed $responseFactory = null, mixed $streamFactory = null)
    {
        $this->client = $client;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        throw new \LogicException('not used');
    }
}

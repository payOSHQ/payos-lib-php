<?php

namespace PayOS\Core;

use PayOS\Exceptions\PayOSException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;
use PsrDiscovery\Discover;

/**
 * PSR-18 HTTP Client wrapper with automatic discovery
 *
 * Redirects (301, 302, 303, 307, 308) are followed by this wrapper, not by the underlying client:
 * `x-api-key`, `x-client-id`, `x-partner-code`, `authorization`, `cookie` and `proxy-authorization` are
 * dropped once a redirect leaves the origin of the original request. Guzzle's PSR-18 `sendRequest()` never
 * follows redirects and a Symfony `Psr18Client` is reconfigured with `max_redirects: 0` when it supports
 * `withOptions()`. A supplied client must not follow redirects itself nor add credential headers, cookies
 * or authentication on its own; otherwise those requests are outside this wrapper's control.
 */
class HTTPClient
{
    private const MAX_REDIRECTS = 5;
    private const SENSITIVE_REDIRECT_HEADERS = [
        'x-api-key',
        'x-client-id',
        'x-partner-code',
        'authorization',
        'cookie',
        'proxy-authorization',
    ];
    private const SYMFONY_PSR18_CLIENT = 'Symfony\Component\HttpClient\Psr18Client';

    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct(
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ) {
        $this->client = $client !== null
            ? self::withoutAutomaticRedirects($client, false)
            : self::withoutAutomaticRedirects($this->discoverClient(), true);
        $this->requestFactory = $requestFactory ?? $this->discoverRequestFactory();
        $this->streamFactory = $streamFactory ?? $this->discoverStreamFactory();
    }

    /**
     * Turns off the redirect following of clients that follow redirects by default, so redirects
     * reach {@see sendRequest()}. A supplied Symfony client without `withOptions()` is kept as is.
     *
     * @throws PayOSException When a discovered client cannot be configured not to follow redirects.
     */
    private static function withoutAutomaticRedirects(ClientInterface $client, bool $discovered): ClientInterface
    {
        $symfonyClient = self::SYMFONY_PSR18_CLIENT;
        if (!$client instanceof $symfonyClient) {
            return $client;
        }
        // symfony/http-client is an optional dependency, so its classes are unknown to static analysis.
        if (method_exists($client, 'withOptions')) {
            /** @var ClientInterface */
            return $client->withOptions(['max_redirects' => 0]); // @phpstan-ignore class.notFound
        }
        if (!$discovered) {
            return $client;
        }
        $symfonyHttpClient = 'Symfony\Component\HttpClient\HttpClient';
        if (!class_exists($symfonyHttpClient)) {
            throw new PayOSException('Cannot configure the discovered Symfony HTTP client not to follow redirects');
        }

        /** @var ClientInterface */
        return new $symfonyClient( // @phpstan-ignore class.notFound
            $symfonyHttpClient::create(['max_redirects' => 0]),
            Discover::httpResponseFactory(),
            Discover::httpStreamFactory()
        );
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
     * Send a PSR-7 request and get response, following up to 5 redirects (301, 302, 303, 307, 308).
     *
     * Credentials are dropped once a redirect leaves the origin (scheme, host and port) of the original
     * request and stay dropped for the rest of the chain. A 303, or a 301/302 answering a POST, is
     * followed with a body-less GET.
     *
     * @throws PayOSException When there are more than 5 redirects or the location is not an HTTP(S) URL.
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        for ($hop = 0; ; $hop++) {
            $response = $this->client->sendRequest($request);
            $status = $response->getStatusCode();
            $location = in_array($status, [301, 302, 303, 307, 308], true) ? $response->getHeaderLine('Location') : '';
            if ($location === '') {
                return $response;
            }
            $response->getBody()->close();
            if ($hop >= self::MAX_REDIRECTS) {
                throw new PayOSException(sprintf('Too many redirects (more than %d)', self::MAX_REDIRECTS));
            }

            $next = self::resolveLocation($request->getUri(), $location);
            if (self::origin($next) !== self::origin($request->getUri())) {
                foreach (self::SENSITIVE_REDIRECT_HEADERS as $name) {
                    $request = $request->withoutHeader($name);
                }
            }

            $method = strtoupper($request->getMethod());
            if ($status === 303 || (($status === 301 || $status === 302) && $method === 'POST')) {
                $request = $request->withMethod('GET')
                    ->withBody($this->streamFactory->createStream(''))
                    ->withoutHeader('Content-Type')
                    ->withoutHeader('Content-Length');
            } elseif ($request->getBody()->isSeekable()) {
                $request->getBody()->rewind();
            }

            $request = $request->withUri($next);
        }
    }

    /**
     * Resolves a `Location` header against the URI of the request (RFC 3986, section 5.2).
     *
     * @throws PayOSException When the location is not an HTTP(S) URL.
     */
    private static function resolveLocation(UriInterface $base, string $location): UriInterface
    {
        $parts = parse_url($location);
        if ($parts === false) {
            throw new PayOSException('Invalid redirect location');
        }

        $uri = $base;
        $query = $parts['query'] ?? '';
        if (isset($parts['scheme']) || isset($parts['host'])) {
            $scheme = strtolower($parts['scheme'] ?? $base->getScheme());
            if (($scheme !== 'http' && $scheme !== 'https') || !isset($parts['host'])) {
                throw new PayOSException('Unsupported redirect location');
            }
            $uri = $uri->withScheme($scheme)
                ->withUserInfo($parts['user'] ?? '', $parts['pass'] ?? null)
                ->withHost($parts['host'])
                ->withPort($parts['port'] ?? null);
            $path = self::removeDotSegments($parts['path'] ?? '');
        } elseif (($parts['path'] ?? '') === '') {
            $path = $base->getPath();
            $query = $parts['query'] ?? $base->getQuery();
        } elseif ($parts['path'][0] === '/') {
            $path = self::removeDotSegments($parts['path']);
        } else {
            $basePath = $base->getPath();
            $directory = $basePath === '' ? '/' : substr($basePath, 0, (int) strrpos($basePath, '/') + 1);
            $path = self::removeDotSegments($directory . $parts['path']);
        }

        return $uri->withPath($path)->withQuery($query)->withFragment($parts['fragment'] ?? '');
    }

    /**
     * Removes `.` and `..` segments from a path (RFC 3986, section 5.2.4).
     */
    private static function removeDotSegments(string $path): string
    {
        $output = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                if (count($output) > 1) {
                    array_pop($output);
                }
            } elseif ($segment !== '.') {
                $output[] = $segment;
            }
        }
        $result = implode('/', $output);
        $last = substr($path, -2) === '/.' || substr($path, -3) === '/..' || $path === '.' || $path === '..';
        if ($last && !str_ends_with($result, '/')) {
            $result .= '/';
        }
        if (str_starts_with($path, '/') && !str_starts_with($result, '/')) {
            $result = '/' . $result;
        }

        return $result;
    }

    /**
     * Scheme, host and port of a URI, with the default port made explicit.
     */
    private static function origin(UriInterface $uri): string
    {
        $scheme = strtolower($uri->getScheme());
        $port = $uri->getPort() ?? ($scheme === 'https' ? 443 : 80);

        return $scheme . '://' . strtolower($uri->getHost()) . ':' . $port;
    }

    /**
     * Get the underlying PSR-18 client
     */
    public function getClient(): ClientInterface
    {
        return $this->client;
    }
}

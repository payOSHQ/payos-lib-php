<?php

namespace PayOS\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PayOS\Core\HTTPClient;
use PayOS\Exceptions\PayOSException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

class HTTPClientTest extends TestCase
{
    public function testHTTPClientInstantiatesWithDiscovery(): void
    {
        $client = new HTTPClient();
        $this->assertInstanceOf(HTTPClient::class, $client);
        $this->assertInstanceOf(ClientInterface::class, $client->getClient());
    }

    public function testHTTPClientCanCreateRequest(): void
    {
        $client = new HTTPClient();
        $request = $client->createRequest('GET', 'https://api.example.com/test');

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('https://api.example.com/test', (string) $request->getUri());
    }

    public function testHTTPClientCanCreateStream(): void
    {
        $client = new HTTPClient();
        $content = '{"test": "data"}';
        $stream = $client->createStream($content);

        $this->assertEquals($content, (string) $stream);
    }

    /**
     * @param Response[] $responses
     * @param \ArrayObject<int, array> $history
     */
    private static function mockClient(array $responses, \ArrayObject $history): HTTPClient
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new HTTPClient(new Client(['handler' => $stack]), new HttpFactory(), new HttpFactory());
    }

    /**
     * @param \ArrayObject<int, array> $history
     */
    private static function lastRequest(\ArrayObject $history): RequestInterface
    {
        $request = $history[count($history) - 1]['request'] ?? null;
        self::assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }

    private static function credentialedRequest(HTTPClient $client, string $method): RequestInterface
    {
        return $client->createRequest($method, 'https://api-merchant.payos.vn/v2/payment-requests')
            ->withHeader('x-client-id', 'client')
            ->withHeader('x-api-key', 'key')
            ->withHeader('Authorization', 'Bearer t')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($client->createStream('{"a":1}'));
    }

    public function testCrossOriginRedirectDropsCredentials(): void
    {
        $history = new \ArrayObject();
        $client = self::mockClient([
            new Response(307, ['Location' => 'https://other.example/collect']),
            new Response(200, [], 'ok'),
        ], $history);

        $response = $client->sendRequest(self::credentialedRequest($client, 'POST'));

        $this->assertSame(200, $response->getStatusCode());
        $redirected = self::lastRequest($history);
        $this->assertSame('https://other.example/collect', (string) $redirected->getUri());
        $this->assertSame('POST', $redirected->getMethod());
        $this->assertSame('{"a":1}', (string) $redirected->getBody());
        foreach (['x-client-id', 'x-api-key', 'authorization'] as $header) {
            $this->assertFalse($redirected->hasHeader($header), $header);
        }
    }

    public function testSameOriginRedirectKeepsCredentialsAndSwitchesPostToGet(): void
    {
        $history = new \ArrayObject();
        $client = self::mockClient([
            new Response(302, ['Location' => '../other?x=1']),
            new Response(200, [], 'ok'),
        ], $history);

        $client->sendRequest(self::credentialedRequest($client, 'POST'));

        $redirected = self::lastRequest($history);
        $this->assertSame('https://api-merchant.payos.vn/other?x=1', (string) $redirected->getUri());
        $this->assertSame('GET', $redirected->getMethod());
        $this->assertSame('', (string) $redirected->getBody());
        $this->assertFalse($redirected->hasHeader('Content-Type'));
        $this->assertSame('key', $redirected->getHeaderLine('x-api-key'));
        $this->assertSame('client', $redirected->getHeaderLine('x-client-id'));
    }

    private const CREDENTIAL_HEADERS = ['x-client-id', 'x-api-key', 'x-partner-code', 'authorization', 'cookie', 'proxy-authorization'];

    private static function allCredentialHeadersRequest(HTTPClient $client): RequestInterface
    {
        return $client->createRequest('GET', 'https://api-merchant.payos.vn/v2/payment-requests/x')
            ->withHeader('x-client-id', 'client')
            ->withHeader('x-api-key', 'key')
            ->withHeader('x-partner-code', 'partner')
            ->withHeader('Authorization', 'Bearer t')
            ->withHeader('Cookie', 'sid=1')
            ->withHeader('Proxy-Authorization', 'Basic p');
    }

    public function testCrossOriginRedirectRemovesAllCredentialHeaders(): void
    {
        foreach (['https://other.example/x', 'http://api-merchant.payos.vn/x', 'https://api-merchant.payos.vn:8443/x'] as $location) {
            $history = new \ArrayObject();
            $client = self::mockClient([new Response(302, ['Location' => $location]), new Response(200, [], 'ok')], $history);

            $client->sendRequest(self::allCredentialHeadersRequest($client));

            $redirected = self::lastRequest($history);
            $this->assertSame($location, (string) $redirected->getUri());
            foreach (self::CREDENTIAL_HEADERS as $header) {
                $this->assertFalse($redirected->hasHeader($header), "{$location} {$header}");
            }
        }
    }

    public function testSameOriginRedirectKeepsAllCredentialHeaders(): void
    {
        $history = new \ArrayObject();
        $client = self::mockClient([new Response(301, ['Location' => '/v2/other']), new Response(200, [], 'ok')], $history);

        $client->sendRequest(self::allCredentialHeadersRequest($client));

        $redirected = self::lastRequest($history);
        foreach (self::CREDENTIAL_HEADERS as $header) {
            $this->assertTrue($redirected->hasHeader($header), $header);
        }
        $this->assertSame('partner', $redirected->getHeaderLine('x-partner-code'));
    }

    public function testCredentialHeadersStayRemovedAfterReturningToOrigin(): void
    {
        $history = new \ArrayObject();
        $client = self::mockClient([
            new Response(302, ['Location' => 'https://other.example/x']),
            new Response(302, ['Location' => 'https://api-merchant.payos.vn/back']),
            new Response(200, [], 'ok'),
        ], $history);

        $client->sendRequest(self::allCredentialHeadersRequest($client));

        $redirected = self::lastRequest($history);
        $this->assertSame('https://api-merchant.payos.vn/back', (string) $redirected->getUri());
        foreach (self::CREDENTIAL_HEADERS as $header) {
            $this->assertFalse($redirected->hasHeader($header), $header);
        }
    }

    public function testTooManyRedirectsThrows(): void
    {
        $history = new \ArrayObject();
        $client = self::mockClient(array_fill(0, 6, new Response(302, ['Location' => '/loop'])), $history);

        $this->expectException(PayOSException::class);
        $this->expectExceptionMessage('Too many redirects');

        $client->sendRequest($client->createRequest('GET', 'https://api-merchant.payos.vn/start'));
    }
}

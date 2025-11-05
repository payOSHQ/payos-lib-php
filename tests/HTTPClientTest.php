<?php

namespace PayOS\Tests;

use PayOS\Core\HTTPClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

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
}

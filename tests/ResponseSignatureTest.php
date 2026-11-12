<?php

namespace PayOS\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PayOS\Core\HTTPClient;
use PayOS\Exceptions\InvalidSignatureException;
use PayOS\PayOS;
use PHPUnit\Framework\TestCase;

class ResponseSignatureTest extends TestCase
{
    private static function payos(): PayOS
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"code":"00","desc":"success","data":{"id":"x"}}'),
        ]);
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);
        $httpClient = new HTTPClient($guzzle, new HttpFactory(), new HttpFactory());

        return new PayOS(clientId: 'c', apiKey: 'a', checksumKey: 'k', httpClient: $httpClient);
    }

    public function testBodySignatureMissingFailsClosed(): void
    {
        $this->expectException(InvalidSignatureException::class);
        $this->expectExceptionMessage('Missing response signature');

        self::payos()->paymentRequests->invoices->get('x');
    }

    public function testHeaderSignatureMissingFailsClosed(): void
    {
        $this->expectException(InvalidSignatureException::class);
        $this->expectExceptionMessage('Missing response signature');

        self::payos()->payoutsAccount->balance();
    }
}

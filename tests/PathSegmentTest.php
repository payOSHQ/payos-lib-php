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
use PayOS\PayOS;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class PathSegmentTest extends TestCase
{
    /** @var \ArrayObject<int, array> */
    private \ArrayObject $history;

    private function payos(): PayOS
    {
        $this->history = new \ArrayObject();
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"code":"00","desc":"success","data":{"id":"x"}}'),
        ]));
        $stack->push(Middleware::history($this->history));
        $httpClient = new HTTPClient(new Client(['handler' => $stack]), new HttpFactory(), new HttpFactory());

        return new PayOS(
            clientId: 'c',
            apiKey: 'a',
            checksumKey: 'k',
            baseURL: 'https://api-merchant.payos.vn',
            maxRetries: 0,
            httpClient: $httpClient
        );
    }

    /**
     * @return array<string, array{0: callable(PayOS, string|int): mixed, 1: string}>
     */
    private static function methods(): array
    {
        return [
            'paymentRequests.get' => [
                static fn (PayOS $p, $id) => $p->paymentRequests->get($id),
                '/v2/payment-requests/%s',
            ],
            'paymentRequests.cancel' => [
                static fn (PayOS $p, $id) => $p->paymentRequests->cancel($id),
                '/v2/payment-requests/%s/cancel',
            ],
            'invoices.get' => [
                static fn (PayOS $p, $id) => $p->paymentRequests->invoices->get($id),
                '/v2/payment-requests/%s/invoices',
            ],
            'invoices.download.id' => [
                static fn (PayOS $p, $id) => $p->paymentRequests->invoices->download('inv1', $id),
                '/v2/payment-requests/%s/invoices/inv1/download',
            ],
            'invoices.download.invoiceId' => [
                static fn (PayOS $p, $id) => $p->paymentRequests->invoices->download((string) $id, 'pl1'),
                '/v2/payment-requests/pl1/invoices/%s/download',
            ],
            'payouts.get' => [
                static fn (PayOS $p, $id) => $p->payouts->get((string) $id),
                '/v1/payouts/%s',
            ],
        ];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function encodedIdProvider(): iterable
    {
        $ids = [
            'normal id' => ['abc123', 'abc123'],
            'dot segment prefix' => ['../x', '..%2Fx'],
            'slash' => ['a/b', 'a%2Fb'],
            'question mark' => ['a?b', 'a%3Fb'],
            'hash' => ['a#b', 'a%23b'],
            'already encoded slash' => ['a%2Fb', 'a%252Fb'],
        ];
        foreach (array_keys(self::methods()) as $method) {
            foreach ($ids as $label => [$id, $encoded]) {
                yield "{$method} {$label}" => [$method, $id, $encoded];
            }
        }
    }

    private function lastRequest(): RequestInterface
    {
        $request = $this->history[count($this->history) - 1]['request'] ?? null;
        $this->assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }

    #[DataProvider('encodedIdProvider')]
    public function testIdIsEncodedAsSingleSegment(string $method, string $id, string $encoded): void
    {
        [$call, $pattern] = self::methods()[$method];
        $payos = $this->payos();

        try {
            $call($payos, $id);
        } catch (\Throwable) {
            // Only the outgoing request matters here; the mocked response is not signed.
        }

        $this->assertCount(1, $this->history);
        $uri = $this->lastRequest()->getUri();
        $this->assertSame(sprintf($pattern, $encoded), $uri->getPath());
        $this->assertSame('', $uri->getQuery());
        $this->assertSame('', $uri->getFragment());
    }

    /**
     * @return iterable<string, array{string, string|int, string}>
     */
    public static function rejectedIdProvider(): iterable
    {
        $names = [
            'paymentRequests.get' => 'id',
            'paymentRequests.cancel' => 'id',
            'invoices.get' => 'id',
            'invoices.download.id' => 'id',
            'invoices.download.invoiceId' => 'invoiceId',
            'payouts.get' => 'payoutId',
        ];
        foreach ($names as $method => $name) {
            yield "{$method} single dot" => [$method, '.', "{$name} must not be a dot segment"];
            yield "{$method} double dot" => [$method, '..', "{$name} must not be a dot segment"];
            yield "{$method} empty" => [$method, '', "{$name} must be a non-empty string or a safe integer"];
        }
        foreach (['paymentRequests.get', 'paymentRequests.cancel', 'invoices.get', 'invoices.download.id'] as $method) {
            yield "{$method} negative order code" => [$method, -1, 'id must be a non-negative safe integer'];
            yield "{$method} order code above 2^53-1" => [$method, 9007199254740992, 'id must be a non-negative safe integer'];
        }
    }

    #[DataProvider('rejectedIdProvider')]
    public function testInvalidIdIsRejectedBeforeSending(string $method, string|int $id, string $message): void
    {
        [$call] = self::methods()[$method];
        $payos = $this->payos();

        try {
            $call($payos, $id);
            $this->fail('Expected PayOSException');
        } catch (PayOSException $e) {
            $this->assertSame($message, $e->getMessage());
        }
        $this->assertCount(0, $this->history);
    }

    public function testLargestSafeOrderCodeIsAccepted(): void
    {
        $payos = $this->payos();

        try {
            $payos->paymentRequests->get(9007199254740991);
        } catch (\Throwable) {
        }

        $this->assertSame('/v2/payment-requests/9007199254740991', $this->lastRequest()->getUri()->getPath());
    }
}

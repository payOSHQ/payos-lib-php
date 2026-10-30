<?php

namespace PayOS\Tests\Webhooks;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use PayOS\Core\HTTPClient;
use PayOS\Core\ObjectSerializer;
use PayOS\Exceptions\WebhookException;
use PayOS\Models\Webhooks\Webhook;
use PayOS\PayOS;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WebhookVectorsTest extends TestCase
{
    private static function fixture(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../fixtures/webhook-vectors.json'), true);
    }

    private static function payos(): PayOS
    {
        $guzzle = new Client(['handler' => HandlerStack::create(new MockHandler())]);
        $httpClient = new HTTPClient($guzzle, new HttpFactory(), new HttpFactory());

        return new PayOS(
            clientId: 'c',
            apiKey: 'a',
            checksumKey: self::fixture()['checksumKey'],
            httpClient: $httpClient
        );
    }

    public static function wireCases(): array
    {
        $cases = [];
        foreach (self::fixture()['cases'] as $case) {
            $cases[$case['id']] = [$case];
        }

        return $cases;
    }

    public static function typedCases(): array
    {
        $cases = [];
        foreach (self::fixture()['cases'] as $case) {
            if ($case['typed']) {
                $cases[$case['id']] = [$case];
            }
        }

        return $cases;
    }

    #[DataProvider('wireCases')]
    public function testWirePath(array $case): void
    {
        $body = json_decode($case['body'], true);

        if ($case['expect'] === 'reject') {
            $this->expectException(WebhookException::class);
            if (!str_starts_with($case['id'], 'R14') && !str_starts_with($case['id'], 'R15')) {
                $this->expectExceptionMessageMatches('/^Invalid webhook data/');
            }
        }

        $result = self::payos()->webhooks->verify($body, ['asArray' => true]);

        $this->assertSame($body['data']['orderCode'], $result['orderCode']);
    }

    #[DataProvider('typedCases')]
    public function testTypedPath(array $case): void
    {
        $body = json_decode($case['body'], true);

        if ($case['expect'] === 'reject') {
            $this->expectException(WebhookException::class);
        }

        $model = ObjectSerializer::fromArray($body, Webhook::class);
        $result = self::payos()->webhooks->verify($model);

        $this->assertSame($body['data']['orderCode'], $result->orderCode);
    }
}

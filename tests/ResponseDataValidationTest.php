<?php

namespace PayOS\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PayOS\Core\HTTPClient;
use PayOS\Crypto\CryptoProvider;
use PayOS\Exceptions\InvalidSignatureException;
use PayOS\PayOS;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResponseDataValidationTest extends TestCase
{
    private const CHECKSUM_KEY = 'checksum-key';

    private static function payos(string $body): PayOS
    {
        $mock = new MockHandler([new Response(200, ['Content-Type' => 'application/json'], $body)]);
        $httpClient = new HTTPClient(new Client(['handler' => HandlerStack::create($mock)]), new HttpFactory(), new HttpFactory());

        return new PayOS(
            clientId: 'c',
            apiKey: 'a',
            checksumKey: self::CHECKSUM_KEY,
            baseURL: 'https://api-merchant.payos.vn',
            maxRetries: 0,
            httpClient: $httpClient
        );
    }

    /** @param array<string, mixed> $data */
    private static function signedBody(array $data): string
    {
        $signature = (new CryptoProvider())->createSignatureFromObj($data, self::CHECKSUM_KEY);

        return (string) json_encode(['code' => '00', 'desc' => 'success', 'data' => $data, 'signature' => $signature]);
    }

    /** @return array<string, mixed> */
    private static function paymentLinkData(): array
    {
        return [
            'id' => '0c1f5c3e8d0a4b7c9e2f1a3b5c7d9e0f',
            'orderCode' => 123456,
            'amount' => 10000,
            'amountPaid' => 10000,
            'amountRemaining' => 0,
            'status' => 'PAID',
            'createdAt' => '2026-01-01T10:00:00+07:00',
            'transactions' => [[
                'reference' => 'FT123',
                'amount' => 10000,
                'accountNumber' => '0123456789',
                'description' => 'Thanh toan don hang 123456',
                'transactionDateTime' => '2026-01-01T10:01:00+07:00',
                'virtualAccountName' => null,
                'virtualAccountNumber' => null,
                'counterAccountBankId' => null,
                'counterAccountBankName' => null,
                'counterAccountName' => null,
                'counterAccountNumber' => null,
            ]],
            'canceledAt' => null,
            'cancellationReason' => null,
        ];
    }

    public function testSignedPaymentLinkIsAccepted(): void
    {
        $link = self::payos(self::signedBody(self::paymentLinkData()))->paymentRequests->get(123456, ['asArray' => true]);

        $this->assertSame('0c1f5c3e8d0a4b7c9e2f1a3b5c7d9e0f', is_array($link) ? $link['id'] : $link->id);
    }

    public function testSignedInvoiceListIsAccepted(): void
    {
        $data = [
            'invoices' => [[
                'invoiceId' => 'INV001',
                'invoiceNumber' => '0000001',
                'issuedTimestamp' => 1767236400,
                'issuedDatetime' => '2026-01-01T10:00:00+07:00',
                'transactionId' => 'TX1',
                'reservationCode' => 'R1',
                'codeOfTax' => null,
            ]],
            'taxInfo' => null,
        ];

        $result = self::payos(self::signedBody($data))->paymentRequests->invoices->get('pl1');

        $this->assertNotNull($result);
    }

    public function testAmpersandKeySequenceIsRejectedEvenWhenSigned(): void
    {
        $data = self::paymentLinkData();
        $data['cancellationReason'] = 'reason&status=PAID';

        $this->expectException(InvalidSignatureException::class);
        $this->expectExceptionMessage('Invalid response data: "cancellationReason" must not contain a "&key=" sequence');

        self::payos(self::signedBody($data))->paymentRequests->get(123456);
    }

    public function testAmpersandKeySequenceInListItemIsRejectedEvenWhenSigned(): void
    {
        $data = self::paymentLinkData();
        $data['transactions'][0]['description'] = 'x&amount=1';

        $this->expectException(InvalidSignatureException::class);
        $this->expectExceptionMessage('Invalid response data: "transactions[0].description" must not contain a "&key=" sequence');

        self::payos(self::signedBody($data))->paymentRequests->get(123456);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedDataProvider(): iterable
    {
        yield 'key with equals sign' => ['{"id":"x","a=b":"1"}', 'unsupported key "a=b"'];
        yield 'key with ampersand' => ['{"id":"x","a&b":"1"}', 'unsupported key "a&b"'];
        yield 'proto key' => ['{"id":"x","__proto__":"1"}', 'unsupported key "__proto__"'];
        yield 'nested object' => ['{"id":"x","status":{"a":1}}', '"status" must not be an object'];
        yield 'nested list inside list' => ['{"id":"x","transactions":[[1]]}', '"transactions[0]" has an unsupported type'];
        yield 'object inside list item' => ['{"id":"x","transactions":[{"a":{"b":1}}]}', '"transactions[0].a" has an unsupported type'];
        yield 'bad key inside list item' => ['{"id":"x","transactions":[{"a-b":1}]}', 'unsupported key "transactions[0].a-b"'];
        yield 'non-finite number' => ['{"id":"x","amount":1e400}', '"amount" must be a finite number within the safe integer range'];
        yield 'unsafe integer' => ['{"id":"x","amount":9007199254740993}', '"amount" must be a safe integer'];
        yield 'unsafe integral float' => ['{"id":"x","amount":9007199254740993.0}', '"amount" must be a finite number within the safe integer range'];
    }

    #[DataProvider('rejectedDataProvider')]
    public function testUnsupportedDataIsRejectedBeforeSignatureCheck(string $dataJson, string $reason): void
    {
        $body = '{"code":"00","desc":"success","data":' . $dataJson . ',"signature":"0000"}';

        $this->expectException(InvalidSignatureException::class);
        $this->expectExceptionMessage('Invalid response data: ' . $reason);

        self::payos($body)->paymentRequests->get(123456);
    }
}

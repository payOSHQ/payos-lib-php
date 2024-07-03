<?php

use PHPUnit\Framework\TestCase;
use PayOS\PayOS;

class PayosTest extends TestCase
{
    private $clientId = "your_client_id";
    private $apiKey = "your_api_key";
    private $checksumKey = "your_checksum_key";
    private $webhookUrl = "https://your_webhook_url";
    private $orderCode;

    private $payOS;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payOS = new PayOS($this->clientId, $this->apiKey, $this->checksumKey);
    }

    public function testPaymentLink()
    {
        // Create payment link
        $this->orderCode = intval(substr(strval(microtime(true) * 10000), -6));
        $paymentData = [
            'orderCode' => $this->orderCode,
            'amount' => 2000,
            'description' => 'Thanh toán đơn hàng',
            'items' => array(
                0 => array(
                    "price" => 2000,
                    "name" => "Mỳ tôm",
                    'quantity' => 1
                ),
            ),
            'cancelUrl' => $this->webhookUrl,
            'returnUrl' => $this->webhookUrl,
        ];
        $createResult = $this->payOS->createPaymentLink($paymentData);
        $this->assertEquals($this->orderCode, $createResult['orderCode']);

        // Get payment link information
        $getResult = $this->payOS->getPaymentLinkInformation($this->orderCode);
        $this->assertEquals($this->orderCode, $getResult['orderCode']);

        // Cancel payment link
        $cancelResult = $this->payOS->cancelPaymentLink($this->orderCode);
        $this->assertEquals($this->orderCode, $cancelResult['orderCode']);
    }

    public function testWebhook()
    {
        // Confirm webhook
        $confirmedWebhook = $this->payOS->confirmWebhook($this->webhookUrl);
        $this->assertEquals($this->webhookUrl, $confirmedWebhook);

        // Verify webhook
        $webhookData = [
            'orderCode' => 123,
            'amount' => 3000,
            'description' => 'VQRIO123',
            'accountNumber' => '12345678',
            'reference' => 'TF230204212323',
            'transactionDateTime' => '2023-02-04 18:25:00',
            'currency' => 'VND',
            'paymentLinkId' => '124c33293c43417ab7879e14c8d9eb18',
            'code' => '00',
            'desc' => 'Thành công',
            'counterAccountBankId' => '',
            'counterAccountBankName' => '',
            'counterAccountName' => '',
            'counterAccountNumber' => '',
            'virtualAccountName' => '',
            'virtualAccountNumber' => '',
        ];

        $webhookBody = [
            'code' => '00',
            'desc' => 'success',
            'data' => $webhookData,
            'signature' => '8b50051f80b534f8a54b457a6e0ed6847e07b138035ec22cea65a8a167fbbe14'
        ];

        $verifiedWebhookData = $this->payOS->verifyPaymentWebhookData($webhookBody);
        $this->assertEquals($webhookData, $verifiedWebhookData);
    }
}

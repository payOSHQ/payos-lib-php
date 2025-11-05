<?php

namespace PayOS\Models\V2\PaymentRequests;

class CreatePaymentLinkResponse
{
    public string $bin;
    public string $accountNumber;
    public string $accountName;
    public int $amount;
    public string $description;
    public int $orderCode;
    public string $currency;
    public string $paymentLinkId;
    public PaymentLinkStatus $status;
    public ?int $expiredAt = null;
    public string $checkoutUrl;
    public string $qrCode;

    public function __construct(
        string $bin,
        string $accountNumber,
        string $accountName,
        int $amount,
        string $description,
        int $orderCode,
        string $currency,
        string $paymentLinkId,
        PaymentLinkStatus $status,
        string $checkoutUrl,
        string $qrCode,
        ?int $expiredAt = null
    ) {
        $this->bin = $bin;
        $this->accountNumber = $accountNumber;
        $this->accountName = $accountName;
        $this->amount = $amount;
        $this->description = $description;
        $this->orderCode = $orderCode;
        $this->currency = $currency;
        $this->paymentLinkId = $paymentLinkId;
        $this->status = $status;
        $this->checkoutUrl = $checkoutUrl;
        $this->qrCode = $qrCode;
        $this->expiredAt = $expiredAt;
    }
}

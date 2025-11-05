<?php

namespace PayOS\Models\V2\PaymentRequests;

class CreatePaymentLinkRequest
{
    public int $orderCode;
    public int $amount;
    public string $description;
    public string $cancelUrl;
    public string $returnUrl;
    public ?string $signature = null;
    /** @var PaymentLinkItem[]|null */
    public ?array $items = null;
    public ?string $buyerName = null;
    public ?string $buyerCompanyName = null;
    public ?string $buyerTaxCode = null;
    public ?string $buyerEmail = null;
    public ?string $buyerPhone = null;
    public ?string $buyerAddress = null;
    public ?InvoiceRequest $invoice = null;
    public ?int $expiredAt = null;

    public function __construct(
        int $orderCode,
        int $amount,
        string $description,
        string $cancelUrl,
        string $returnUrl,
        ?string $signature = null,
        ?array $items = null,
        ?string $buyerName = null,
        ?string $buyerCompanyName = null,
        ?string $buyerTaxCode = null,
        ?string $buyerEmail = null,
        ?string $buyerPhone = null,
        ?string $buyerAddress = null,
        ?InvoiceRequest $invoice = null,
        ?int $expiredAt = null
    ) {
        $this->orderCode = $orderCode;
        $this->amount = $amount;
        $this->description = $description;
        $this->cancelUrl = $cancelUrl;
        $this->returnUrl = $returnUrl;
        $this->signature = $signature;
        $this->items = $items;
        $this->buyerName = $buyerName;
        $this->buyerCompanyName = $buyerCompanyName;
        $this->buyerTaxCode = $buyerTaxCode;
        $this->buyerEmail = $buyerEmail;
        $this->buyerPhone = $buyerPhone;
        $this->buyerAddress = $buyerAddress;
        $this->invoice = $invoice;
        $this->expiredAt = $expiredAt;
    }
}

<?php

namespace PayOS\Models\Webhooks;

class WebhookData
{
    public int $orderCode;
    public int $amount;
    public string $description;
    public string $accountNumber;
    public string $reference;
    public string $transactionDateTime;
    public string $currency;
    public string $paymentLinkId;
    public string $code;
    public string $desc;
    public ?string $counterAccountBankId;
    public ?string $counterAccountBankName;
    public ?string $counterAccountName;
    public ?string $counterAccountNumber;
    public ?string $virtualAccountName;
    public ?string $virtualAccountNumber;

    public function __construct(
        int $orderCode,
        int $amount,
        string $description,
        string $accountNumber,
        string $reference,
        string $transactionDateTime,
        string $currency,
        string $paymentLinkId,
        string $code,
        string $desc,
        ?string $counterAccountBankId = null,
        ?string $counterAccountBankName = null,
        ?string $counterAccountName = null,
        ?string $counterAccountNumber = null,
        ?string $virtualAccountName = null,
        ?string $virtualAccountNumber = null
    ) {
        $this->orderCode = $orderCode;
        $this->amount = $amount;
        $this->description = $description;
        $this->accountNumber = $accountNumber;
        $this->reference = $reference;
        $this->transactionDateTime = $transactionDateTime;
        $this->currency = $currency;
        $this->paymentLinkId = $paymentLinkId;
        $this->code = $code;
        $this->desc = $desc;
        $this->counterAccountBankId = $counterAccountBankId;
        $this->counterAccountBankName = $counterAccountBankName;
        $this->counterAccountName = $counterAccountName;
        $this->counterAccountNumber = $counterAccountNumber;
        $this->virtualAccountName = $virtualAccountName;
        $this->virtualAccountNumber = $virtualAccountNumber;
    }
}

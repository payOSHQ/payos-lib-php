<?php

namespace PayOS\Models\V2\PaymentRequests;

class Transaction
{
    public string $reference;
    public int $amount;
    public string $accountNumber;
    public string $description;
    public string $transactionDateTime;
    public ?string $virtualAccountName = null;
    public ?string $virtualAccountNumber = null;
    public ?string $counterAccountBankId = null;
    public ?string $counterAccountBankName = null;
    public ?string $counterAccountName = null;
    public ?string $counterAccountNumber = null;

    public function __construct(
        string $reference,
        int $amount,
        string $accountNumber,
        string $description,
        string $transactionDateTime,
        ?string $virtualAccountName = null,
        ?string $virtualAccountNumber = null,
        ?string $counterAccountBankId = null,
        ?string $counterAccountBankName = null,
        ?string $counterAccountName = null,
        ?string $counterAccountNumber = null
    ) {
        $this->reference = $reference;
        $this->amount = $amount;
        $this->accountNumber = $accountNumber;
        $this->description = $description;
        $this->transactionDateTime = $transactionDateTime;
        $this->virtualAccountName = $virtualAccountName;
        $this->virtualAccountNumber = $virtualAccountNumber;
        $this->counterAccountBankId = $counterAccountBankId;
        $this->counterAccountBankName = $counterAccountBankName;
        $this->counterAccountName = $counterAccountName;
        $this->counterAccountNumber = $counterAccountNumber;
    }
}

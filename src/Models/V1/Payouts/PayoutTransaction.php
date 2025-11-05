<?php

namespace PayOS\Models\V1\Payouts;

class PayoutTransaction
{
    public string $id;
    public string $referenceId;
    public int $amount;
    public string $description;
    public string $toBin;
    public string $toAccountNumber;
    public ?string $toAccountName;
    public ?string $reference;
    public ?string $transactionDatetime;
    public ?string $errorMessage;
    public ?string $errorCode;
    public PayoutTransactionState $state;

    public function __construct(
        string $id,
        string $referenceId,
        int $amount,
        string $description,
        string $toBin,
        string $toAccountNumber,
        ?string $toAccountName,
        ?string $reference,
        ?string $transactionDatetime,
        ?string $errorMessage,
        ?string $errorCode,
        PayoutTransactionState $state
    ) {
        $this->id = $id;
        $this->referenceId = $referenceId;
        $this->amount = $amount;
        $this->description = $description;
        $this->toBin = $toBin;
        $this->toAccountNumber = $toAccountNumber;
        $this->toAccountName = $toAccountName;
        $this->reference = $reference;
        $this->transactionDatetime = $transactionDatetime;
        $this->errorMessage = $errorMessage;
        $this->errorCode = $errorCode;
        $this->state = $state;
    }
}

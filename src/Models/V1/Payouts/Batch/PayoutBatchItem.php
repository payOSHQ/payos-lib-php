<?php

namespace PayOS\Models\V1\Payouts\Batch;

class PayoutBatchItem
{
    public string $referenceId;
    public int $amount;
    public string $description;
    public string $toBin;
    public string $toAccountNumber;

    public function __construct(
        string $referenceId,
        int $amount,
        string $description,
        string $toBin,
        string $toAccountNumber
    ) {
        $this->referenceId = $referenceId;
        $this->amount = $amount;
        $this->description = $description;
        $this->toBin = $toBin;
        $this->toAccountNumber = $toAccountNumber;
    }
}

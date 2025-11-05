<?php

namespace PayOS\Models\V1\Payouts;

class PayoutRequest
{
    public string $referenceId;
    public int $amount;
    public string $description;
    public string $toBin;
    public string $toAccountNumber;
    /** @var string[] */
    public ?array $category;

    public function __construct(
        string $referenceId,
        int $amount,
        string $description,
        string $toBin,
        string $toAccountNumber,
        ?array $category = null
    ) {
        $this->referenceId = $referenceId;
        $this->amount = $amount;
        $this->description = $description;
        $this->toBin = $toBin;
        $this->toAccountNumber = $toAccountNumber;
        $this->category = $category;
    }
}

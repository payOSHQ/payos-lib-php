<?php

namespace PayOS\Models\V1\PayoutsAccount;

class PayoutAccountInfo
{
    public string $accountNumber;
    public string $accountName;
    public string $currency;
    public string $balance;

    public function __construct(
        string $accountNumber,
        string $accountName,
        string $currency,
        string $balance
    ) {
        $this->accountNumber = $accountNumber;
        $this->accountName = $accountName;
        $this->currency = $currency;
        $this->balance = $balance;
    }
}

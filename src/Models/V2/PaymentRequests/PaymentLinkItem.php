<?php

namespace PayOS\Models\V2\PaymentRequests;

class PaymentLinkItem
{
    public string $name;
    public int $quantity;
    public int $price;
    public ?string $unit = null;
    public ?TaxPercentage $taxPercentage = null;

    public function __construct(
        string $name,
        int $quantity,
        int $price,
        ?string $unit = null,
        ?TaxPercentage $taxPercentage = null
    ) {
        $this->name = $name;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->unit = $unit;
        $this->taxPercentage = $taxPercentage;
    }
}

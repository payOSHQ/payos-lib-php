<?php

namespace PayOS\Models\V1\Payouts;

class EstimateCredit
{
    public int $estimateCredit;

    public function __construct(int $estimateCredit)
    {
        $this->estimateCredit = $estimateCredit;
    }
}

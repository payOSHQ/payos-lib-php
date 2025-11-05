<?php

namespace PayOS\Models\V2\PaymentRequests\Invoices;

class InvoicesInfo
{
    /** @var Invoice[] */
    public array $invoices;

    public function __construct(array $invoices)
    {
        $this->invoices = $invoices;
    }
}

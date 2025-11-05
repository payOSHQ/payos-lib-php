<?php

namespace PayOS\Models\V2\PaymentRequests;

class InvoiceRequest
{
    public ?bool $buyerNotGetInvoice = null;
    public ?TaxPercentage $taxPercentage = null;

    public function __construct(
        ?bool $buyerNotGetInvoice = null,
        ?TaxPercentage $taxPercentage = null
    ) {
        $this->buyerNotGetInvoice = $buyerNotGetInvoice;
        $this->taxPercentage = $taxPercentage;
    }
}

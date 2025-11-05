<?php

namespace PayOS\Models\V2\PaymentRequests\Invoices;

use DateTime;

class Invoice
{
    public string $invoiceId;
    public ?string $invoiceNumber = null;
    public ?int $issuedTimestamp = null;
    public ?DateTime $issuedDatetime = null;
    public ?string $transactionId = null;
    public ?string $reservationCode = null;
    public ?string $codeOfTax = null;

    public function __construct(
        string $invoiceId,
        ?string $invoiceNumber = null,
        ?int $issuedTimestamp = null,
        ?DateTime $issuedDatetime = null,
        ?string $transactionId = null,
        ?string $reservationCode = null,
        ?string $codeOfTax = null
    ) {
        $this->invoiceId = $invoiceId;
        $this->invoiceNumber = $invoiceNumber;
        $this->issuedTimestamp = $issuedTimestamp;
        $this->issuedDatetime = $issuedDatetime;
        $this->transactionId = $transactionId;
        $this->reservationCode = $reservationCode;
        $this->codeOfTax = $codeOfTax;
    }
}

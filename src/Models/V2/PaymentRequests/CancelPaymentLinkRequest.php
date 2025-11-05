<?php

namespace PayOS\Models\V2\PaymentRequests;

class CancelPaymentLinkRequest
{
    public ?string $cancellationReason = null;

    public function __construct(?string $cancellationReason = null)
    {
        $this->cancellationReason = $cancellationReason;
    }
}

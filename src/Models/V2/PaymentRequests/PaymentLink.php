<?php

namespace PayOS\Models\V2\PaymentRequests;

class PaymentLink
{
    public string $id;
    public int $orderCode;
    public int $amount;
    public int $amountPaid;
    public int $amountRemaining;
    public PaymentLinkStatus $status;
    public string $createdAt;
    /** @var Transaction[] */
    public array $transactions;
    public ?string $cancellationReason = null;
    public ?string $canceledAt = null;

    public function __construct(
        string $id,
        int $orderCode,
        int $amount,
        int $amountPaid,
        int $amountRemaining,
        PaymentLinkStatus $status,
        string $createdAt,
        array $transactions,
        ?string $cancellationReason = null,
        ?string $canceledAt = null
    ) {
        $this->id = $id;
        $this->orderCode = $orderCode;
        $this->amount = $amount;
        $this->amountPaid = $amountPaid;
        $this->amountRemaining = $amountRemaining;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->transactions = $transactions;
        $this->cancellationReason = $cancellationReason;
        $this->canceledAt = $canceledAt;
    }
}

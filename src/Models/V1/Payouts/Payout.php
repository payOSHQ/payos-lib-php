<?php

namespace PayOS\Models\V1\Payouts;

class Payout
{
    public string $id;
    public string $referenceId;
    /** @var PayoutTransaction[] */
    public array $transactions;
    /** @var string[] */
    public ?array $category;
    public PayoutApprovalState $approvalState;
    public string $createdAt;

    public function __construct(
        string $id,
        string $referenceId,
        array $transactions,
        ?array $category,
        PayoutApprovalState $approvalState,
        string $createdAt
    ) {
        $this->id = $id;
        $this->referenceId = $referenceId;
        $this->transactions = $transactions;
        $this->category = $category;
        $this->approvalState = $approvalState;
        $this->createdAt = $createdAt;
    }
}

<?php

namespace PayOS\Models\V1\Payouts;

class GetPayoutListParam
{
    public ?string $referenceId;
    public ?PayoutApprovalState $approvalState;
    /** @var string[] */
    public ?array $category;
    public ?\DateTime $fromDate;
    public ?\DateTime $toDate;
    public ?int $limit;
    public ?int $offset;

    public function __construct(
        ?string $referenceId = null,
        ?PayoutApprovalState $approvalState = null,
        ?array $category = null,
        ?\DateTime $fromDate = null,
        ?\DateTime $toDate = null,
        ?int $limit = 10,
        ?int $offset = 0
    ) {
        $this->referenceId = $referenceId;
        $this->approvalState = $approvalState;
        $this->category = $category;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
        $this->limit = $limit;
        $this->offset = $offset;
    }
}

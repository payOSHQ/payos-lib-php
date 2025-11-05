<?php

namespace PayOS\Models\V1\Payouts\Batch;

class PayoutBatchRequest
{
    public string $referenceId;
    public ?bool $validateDestination;
    /** @var string[] */
    public ?array $category;
    /** @var PayoutBatchItem[] */
    public array $payouts;

    public function __construct(
        string $referenceId,
        array $payouts,
        ?array $category = null,
        ?bool $validateDestination = null
    ) {
        $this->referenceId = $referenceId;
        $this->payouts = $payouts;
        $this->category = $category;
        $this->validateDestination = $validateDestination;
    }
}

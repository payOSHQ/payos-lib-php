<?php

namespace PayOS\Models\V1\Payouts;

enum PayoutApprovalState: string
{
    case DRAFTING = 'DRAFTING';
    case SUBMITTED = 'SUBMITTED';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';
    case SCHEDULED = 'SCHEDULED';
    case PROCESSING = 'PROCESSING';
    case FAILED = 'FAILED';
    case PARTIAL_COMPLETED = 'PARTIAL_COMPLETED';
    case COMPLETED = 'COMPLETED';
}

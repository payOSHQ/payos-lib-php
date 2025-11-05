<?php

namespace PayOS\Models\V1\Payouts;

enum PayoutTransactionState: string
{
    case RECEIVED = 'RECEIVED';
    case PROCESSING = 'PROCESSING';
    case CANCELLED = 'CANCELLED';
    case SUCCEEDED = 'SUCCEEDED';
    case ON_HOLD = 'ON_HOLD';
    case REVERSED = 'REVERSED';
    case FAILED = 'FAILED';
}

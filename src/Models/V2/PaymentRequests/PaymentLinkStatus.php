<?php

namespace PayOS\Models\V2\PaymentRequests;

enum PaymentLinkStatus: string
{
    case PENDING = 'PENDING';
    case CANCELLED = 'CANCELLED';
    case UNDERPAID = 'UNDERPAID';
    case PAID = 'PAID';
    case EXPIRED = 'EXPIRED';
    case PROCESSING = 'PROCESSING';
    case FAILED = 'FAILED';
}

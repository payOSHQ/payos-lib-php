<?php

namespace PayOS\Models\V2\PaymentRequests;

enum TaxPercentage: int
{
    case MINUS_TWO = -2;
    case MINUS_ONE = -1;
    case ZERO = 0;
    case FIVE = 5;
    case TEN = 10;
}

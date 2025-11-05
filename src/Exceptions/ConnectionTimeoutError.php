<?php

namespace PayOS\Exceptions;

/**
 * Connection Timeout Exception
 */
class ConnectionTimeoutError extends APIException
{
    public function __construct(?string $message = null)
    {
        parent::__construct(null, null, $message ?? 'Request timed out.', null);
    }
}

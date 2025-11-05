<?php

namespace PayOS\Exceptions;

/**
 * Connection Exception
 */
class ConnectionException extends APIException
{
    public function __construct(?string $message = null)
    {
        parent::__construct(null, null, $message ?? 'Connection error.', null);
    }
}

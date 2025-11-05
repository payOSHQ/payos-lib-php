<?php

namespace PayOS\Exceptions;

use Exception;

/**
 * Base PayOS Exception
 */
class PayOSException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

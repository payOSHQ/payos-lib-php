<?php

namespace PayOS\Exceptions;

/**
 * Invalid Signature Exception
 */
class InvalidSignatureException extends PayOSException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'Invalid signature');
    }
}

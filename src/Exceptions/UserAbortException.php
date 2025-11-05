<?php

namespace PayOS\Exceptions;

/**
 * User Abort Exception
 */
class UserAbortException extends APIException
{
    public function __construct(?string $message = null)
    {
        parent::__construct(null, null, $message ?? 'Request was aborted', null);
    }
}

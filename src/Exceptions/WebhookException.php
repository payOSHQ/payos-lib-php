<?php

namespace PayOS\Exceptions;

/**
 * Webhook Exception
 */
class WebhookException extends PayOSException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'Webhook error');
    }
}

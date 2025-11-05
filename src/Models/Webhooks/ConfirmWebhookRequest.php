<?php

namespace PayOS\Models\Webhooks;

class ConfirmWebhookRequest
{
    public string $webhookUrl;

    public function __construct(string $webhookUrl)
    {
        $this->webhookUrl = $webhookUrl;
    }
}

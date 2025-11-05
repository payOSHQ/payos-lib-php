<?php

namespace PayOS\Models\Webhooks;

class ConfirmWebhookResponse
{
    public string $webhookUrl;
    public string $accountName;
    public string $accountNumber;
    public string $name;
    public string $shortName;

    public function __construct(
        string $webhookUrl,
        string $accountName,
        string $accountNumber,
        string $name,
        string $shortName
    ) {
        $this->webhookUrl = $webhookUrl;
        $this->accountName = $accountName;
        $this->accountNumber = $accountNumber;
        $this->name = $name;
        $this->shortName = $shortName;
    }
}

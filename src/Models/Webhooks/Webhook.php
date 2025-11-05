<?php

namespace PayOS\Models\Webhooks;

class Webhook
{
    public string $code;
    public string $desc;
    public ?bool $success;
    public WebhookData $data;
    public string $signature;

    public function __construct(
        string $code,
        string $desc,
        ?bool $success,
        WebhookData $data,
        string $signature
    ) {
        $this->code = $code;
        $this->desc = $desc;
        $this->success = $success;
        $this->data = $data;
        $this->signature = $signature;
    }
}

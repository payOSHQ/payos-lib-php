<?php

namespace PayOS\Core;

/**
 * API Response structure
 */
class APIResponse
{
    public string $code;
    public string $desc;
    public mixed $data;
    public ?string $signature;

    public function __construct(string $code, string $desc, mixed $data = null, ?string $signature = null)
    {
        $this->code = $code;
        $this->desc = $desc;
        $this->data = $data;
        $this->signature = $signature;
    }

    /**
     * Create APIResponse from array
     */
    public static function fromArray(array $response): self
    {
        return new self(
            $response['code'] ?? '',
            $response['desc'] ?? '',
            $response['data'] ?? null,
            $response['signature'] ?? null
        );
    }
}

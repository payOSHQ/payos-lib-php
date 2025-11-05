<?php

namespace PayOS\Core;

/**
 * Request options for API calls
 */
class RequestOptions
{
    public HTTPMethod $method;
    public string $path;
    public ?array $query;
    public mixed $body;
    public ?array $headers;
    public ?int $maxRetries;
    public ?array $signatureOpts;

    public function __construct(
        HTTPMethod $method,
        string $path,
        ?array $query = null,
        mixed $body = null,
        ?array $headers = null,
        ?int $maxRetries = null,
        ?array $signatureOpts = null
    ) {
        $this->method = $method;
        $this->path = $path;
        $this->query = $query;
        $this->body = $body;
        $this->headers = $headers;
        $this->maxRetries = $maxRetries;
        $this->signatureOpts = $signatureOpts;
    }
}

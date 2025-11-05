<?php

namespace PayOS\Exceptions;

/**
 * API Exception with status, headers, and error details
 */
class APIException extends PayOSException
{
    public ?int $status;
    public ?array $headers;
    public mixed $error;
    public ?string $errorCode;
    public ?string $errorDesc;

    public function __construct(
        ?int $status,
        mixed $error,
        ?string $message,
        ?array $headers = null
    ) {
        $this->status = $status;
        $this->headers = $headers;
        $this->error = $error;

        if (is_array($error)) {
            $this->errorCode = $error['code'] ?? null;
            $this->errorDesc = $error['desc'] ?? null;
        } else {
            $this->errorCode = null;
            $this->errorDesc = null;
        }

        $fullMessage = self::makeMessage($status, $error, $message);
        parent::__construct($fullMessage, $status ?? 0);
    }

    private static function makeMessage(?int $status, mixed $error, ?string $message): string
    {
        $msg = null;

        if (is_array($error) && isset($error['code']) && isset($error['desc'])) {
            $msg = "{$error['desc']} (code: {$error['code']})";
        } elseif (is_array($error) && isset($error['message'])) {
            $msg = is_string($error['message']) ? $error['message'] : json_encode($error['message']);
        } elseif ($error) {
            $msg = is_string($error) ? $error : json_encode($error);
        } else {
            $msg = $message;
        }

        if ($status && $msg) {
            return "HTTP {$status}, {$msg}";
        }
        if ($status) {
            return "HTTP {$status}";
        }
        if ($msg) {
            return $msg;
        }

        return 'No status code or body';
    }

    public static function generateException(
        ?int $status,
        mixed $errorResponse,
        ?string $message,
        ?array $headers
    ): APIException {
        if (!$status || !$headers) {
            return new ConnectionException($message);
        }

        $code = is_array($errorResponse) ? ($errorResponse['code'] ?? null) : null;
        $desc = is_array($errorResponse) ? ($errorResponse['desc'] ?? null) : null;
        $error = is_array($errorResponse) ? ($errorResponse['error'] ?? ['code' => $code, 'desc' => $desc]) : $errorResponse;

        return match ($status) {
            400 => new BadRequestException($status, $error, $message, $headers),
            401 => new UnauthorizedException($status, $error, $message, $headers),
            403 => new ForbiddenException($status, $error, $message, $headers),
            404 => new NotFoundException($status, $error, $message, $headers),
            429 => new TooManyRequestException($status, $error, $message, $headers),
            default => $status >= 500 ? new InternalServerException($status, $error, $message, $headers) : new APIException($status, $error, $message, $headers),
        };
    }
}

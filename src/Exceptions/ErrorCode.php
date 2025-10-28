<?php

namespace PayOS\Exceptions;

class ErrorCode
{
    public const INTERNAL_SERVER_ERROR = "20";
    public const UNAUTHORIZED = "401";
    public const INVALID_PARAMETER = "21";
    public const NO_SIGNATURE = "22";
    public const NO_DATA = "23";
    public const INVALID_SIGNATURE = "24";
    public const DATA_NOT_INTEGRITY = "25";
    public const WEBHOOK_URL_INVALID = "26";
}

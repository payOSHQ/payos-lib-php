<?php

namespace PayOS\Exceptions;

class ErrorCode
{
    const INTERNAL_SERVER_ERROR = "20";
    const UNAUTHORIZED = "401";
    const INVALID_PARAMETER = "21";
    const NO_SIGNATURE = "22";
    const NO_DATA = "23";
    const INVALID_SIGNATURE = "24";
    const DATA_NOT_INTEGRITY = "25";
    const WEBHOOK_URL_INVALID = "26";
}

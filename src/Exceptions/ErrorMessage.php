<?php

namespace PayOS\Exceptions;

/**
 * @deprecated This class is deprecated and will be removed in a future version. Use exception messages from APIException instead.
 * @see \PayOS\Exceptions\APIException::$errorDesc
 */
class ErrorMessage
{
    public const NO_SIGNATURE = "No signature.";
    public const NO_DATA = "No data.";
    public const INVALID_SIGNATURE = "Invalid signature.";
    public const DATA_NOT_INTEGRITY = "The data is unreliable because the signature of the response does not match the signature of the data";
    public const WEBHOOK_URL_INVALID = "Webhook URL invalid.";
    public const UNAUTHORIZED = "Unauthorized.";
    public const INTERNAL_SERVER_ERROR = "Internal Server Error.";
    public const INVALID_PARAMETER = "Invalid Parameter.";
}

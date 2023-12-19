<?php

namespace PayOS\Exceptions;

class ErrorMessage
{
    const NO_SIGNATURE = "No signature.";
    const NO_DATA = "No data.";
    const INVALID_SIGNATURE = "Invalid signature.";
    const DATA_NOT_INTEGRITY = "The data is unreliable because the signature of the response does not match the signature of the data";
    const WEBHOOK_URL_INVALID = "Webhook URL invalid.";
    const UNAUTHORIZED = "Unauthorized.";
    const INTERNAL_SERVER_ERROR = "Internal Server Error.";
    const INVALID_PARAMETER = "Invalid Parameter.";
}

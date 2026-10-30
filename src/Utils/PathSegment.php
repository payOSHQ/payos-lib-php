<?php

namespace PayOS\Utils;

/**
 * Safe path segments for identifiers interpolated into API paths.
 *
 * Identifiers are interpolated into paths such as `/v2/payment-requests/{id}/cancel`. Without
 * encoding, a value like `../../v1/payouts-account/balance` or `x?` re-targets the authenticated
 * request to another endpoint, so strings are percent-encoded and `.` / `..` segments are rejected.
 * Integers must be non-negative and within the safe integer range.
 */
final class PathSegment
{
    private const MAX_SAFE_INTEGER = 9007199254740991;

    /**
     * @param mixed $value
     * @throws \InvalidArgumentException When the value is empty, a dot segment, or not a supported type/range.
     */
    public static function encode(string $name, $value): string
    {
        if (is_int($value)) {
            if ($value < 0 || $value > self::MAX_SAFE_INTEGER) {
                throw new \InvalidArgumentException("{$name} must be a non-negative safe integer");
            }
            return (string) $value;
        }
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("{$name} must be a non-empty string or a safe integer");
        }
        if ($value === '.' || $value === '..') {
            throw new \InvalidArgumentException("{$name} must not be a dot segment");
        }
        return rawurlencode($value);
    }
}

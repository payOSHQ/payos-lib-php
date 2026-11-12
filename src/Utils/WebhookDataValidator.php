<?php

namespace PayOS\Utils;

use PayOS\Exceptions\WebhookException;

/**
 * Validates the shape of webhook `data` before it is hashed.
 *
 * The legacy webhook signature is computed over `key=value` pairs joined by `&` without any
 * encoding, so the payload must not be able to shift delimiters, change value types or rely on
 * the coercion of nested values.
 */
final class WebhookDataValidator
{
    private const REQUIRED_STRING_FIELDS = [
        'accountNumber', 'code', 'currency', 'desc', 'description',
        'paymentLinkId', 'reference', 'transactionDateTime',
    ];

    private const REQUIRED_INTEGER_FIELDS = ['amount', 'orderCode'];

    private const MAX_SAFE_INTEGER = 9007199254740991;

    /**
     * @param mixed $data The raw `data` field of the webhook (array or object).
     * @throws WebhookException When the data does not have the expected shape.
     */
    public static function validate($data): void
    {
        if (is_object($data)) {
            $data = (array) $data;
        }
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw self::invalid('data must be a JSON object');
        }

        foreach ($data as $key => $value) {
            $key = (string) $key;
            if ($key === '' || $key === '__proto__' || preg_match('/^[A-Za-z0-9_]+$/D', $key) !== 1) {
                throw self::invalid(sprintf('unsupported key "%s"', $key));
            }
            if ($value === null) {
                continue;
            }
            if (is_int($value)) {
                if ($value > self::MAX_SAFE_INTEGER || $value < -self::MAX_SAFE_INTEGER) {
                    throw self::invalid(sprintf('"%s" must be a safe integer', $key));
                }
            } elseif (is_string($value)) {
                if (str_contains($value, '=')) {
                    throw self::invalid(sprintf('"%s" must not contain "="', $key));
                }
            } else {
                throw self::invalid(sprintf('"%s" must be a string, an integer or null', $key));
            }
        }

        foreach (self::REQUIRED_INTEGER_FIELDS as $key) {
            if (!isset($data[$key]) || !is_int($data[$key])) {
                throw self::invalid(sprintf('"%s" must be an integer', $key));
            }
        }
        foreach (self::REQUIRED_STRING_FIELDS as $key) {
            if (!isset($data[$key]) || !is_string($data[$key])) {
                throw self::invalid(sprintf('"%s" must be a string', $key));
            }
        }
    }

    private static function invalid(string $reason): WebhookException
    {
        return new WebhookException('Invalid webhook data: ' . $reason);
    }
}

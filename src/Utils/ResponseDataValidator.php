<?php

namespace PayOS\Utils;

use PayOS\Exceptions\InvalidSignatureException;

/**
 * Validates the shape of body-signed response `data` before its signature is computed.
 *
 * The legacy body signature is computed over `key=value` pairs joined by `&` without any
 * encoding, with lists serialised as JSON. The received data must therefore not be able to shift
 * delimiters, replace a scalar with an object, or turn a list into a string that swallows the
 * remaining fields. Top-level values are scalars or lists; objects are only accepted inside lists
 * and may only contain scalars.
 */
final class ResponseDataValidator
{
    private const MAX_SAFE_INTEGER = 9007199254740991;

    /**
     * @param mixed $data The `data` field of a response signed with the `body` signature.
     * @throws InvalidSignatureException When the data does not have the expected shape.
     */
    public static function validate($data): void
    {
        if (is_object($data)) {
            $data = (array) $data;
        }
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw self::invalid('data must be a JSON object');
        }

        self::validateKeys('', $data);
        foreach ($data as $key => $value) {
            $key = (string) $key;
            if (!is_array($value)) {
                self::validateScalar($key, $value);
                continue;
            }
            if ($value !== [] && !array_is_list($value)) {
                throw self::invalid(sprintf('"%s" must not be an object', $key));
            }
            foreach ($value as $index => $item) {
                $path = sprintf('%s[%d]', $key, $index);
                if (is_array($item)) {
                    if ($item !== [] && array_is_list($item)) {
                        throw self::invalid(sprintf('"%s" has an unsupported type', $path));
                    }
                    self::validateKeys($path . '.', $item);
                    foreach ($item as $field => $fieldValue) {
                        self::validateScalar($path . '.' . $field, $fieldValue);
                    }
                } else {
                    self::validateScalar($path, $item);
                }
            }
        }
    }

    /** @param array<array-key, mixed> $record */
    private static function validateKeys(string $path, array $record): void
    {
        foreach (array_keys($record) as $key) {
            $key = (string) $key;
            if ($key === '' || $key === '__proto__' || preg_match('/^[A-Za-z0-9_]+$/D', $key) !== 1) {
                throw self::invalid(sprintf('unsupported key "%s%s"', $path, $key));
            }
        }
    }

    /** @param mixed $value */
    private static function validateScalar(string $path, $value): void
    {
        if ($value === null || is_bool($value)) {
            return;
        }
        if (is_int($value)) {
            if ($value > self::MAX_SAFE_INTEGER || $value < -self::MAX_SAFE_INTEGER) {
                throw self::invalid(sprintf('"%s" must be a safe integer', $path));
            }
        } elseif (is_float($value)) {
            if (!is_finite($value) || ($value == floor($value) && abs($value) > self::MAX_SAFE_INTEGER)) {
                throw self::invalid(sprintf('"%s" must be a finite number within the safe integer range', $path));
            }
        } elseif (is_string($value)) {
            if (preg_match('/&[A-Za-z0-9_]+=/', $value) === 1) {
                throw self::invalid(sprintf('"%s" must not contain a "&key=" sequence', $path));
            }
        } else {
            throw self::invalid(sprintf('"%s" has an unsupported type', $path));
        }
    }

    private static function invalid(string $reason): InvalidSignatureException
    {
        return new InvalidSignatureException('Invalid response data: ' . $reason);
    }
}

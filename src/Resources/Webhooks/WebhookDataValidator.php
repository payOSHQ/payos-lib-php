<?php

namespace PayOS\Resources\Webhooks;

use PayOS\Exceptions\WebhookException;

/**
 * Validates webhook data so that its legacy `key=value&...` signing form parses in only one way.
 */
final class WebhookDataValidator
{
    public const MAX_SAFE = 9007199254740991;

    public const CORE_INT = ['orderCode', 'amount'];

    public const CORE_STR = [
        'description',
        'accountNumber',
        'reference',
        'transactionDateTime',
        'currency',
        'paymentLinkId',
        'code',
        'desc',
    ];

    public const OPTIONAL_STR = [
        'counterAccountBankId',
        'counterAccountBankName',
        'counterAccountName',
        'counterAccountNumber',
        'virtualAccountName',
        'virtualAccountNumber',
    ];

    /**
     * Validate the raw (wire) webhook data array.
     *
     * @throws WebhookException
     */
    public static function validate(mixed $data): void
    {
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            self::fail('data must be an object');
        }

        foreach ($data as $key => $value) {
            // PHP turns numeric-string keys into ints
            $key = (string) $key;
            if (!preg_match('/^[A-Za-z0-9_]+$/D', $key) || $key === '__proto__') {
                self::fail('invalid key');
            }
            if ($value === null) {
                continue;
            }
            if (is_string($value)) {
                if (str_contains($value, '=')) {
                    self::fail("invalid value for {$key}");
                }

                continue;
            }
            if (!self::isSafeInt($value)) {
                self::fail("invalid value for {$key}");
            }
        }

        foreach (self::CORE_INT as $key) {
            if (!array_key_exists($key, $data) || !self::isSafeInt($data[$key])) {
                self::fail("{$key} must be an integer");
            }
        }

        foreach (self::CORE_STR as $key) {
            if (!array_key_exists($key, $data) || !is_string($data[$key])) {
                self::fail("{$key} must be a string");
            }
        }

        foreach (self::OPTIONAL_STR as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && !is_string($data[$key])) {
                self::fail("{$key} must be a string or null");
            }
        }
    }

    /**
     * Validate values of webhook data serialized from a typed model.
     *
     * @throws WebhookException
     */
    public static function validateTypedValues(array $dataArray): void
    {
        foreach ($dataArray as $key => $value) {
            if (is_string($value) && str_contains($value, '=')) {
                self::fail("invalid value for {$key}");
            }
        }

        foreach (self::CORE_INT as $key) {
            if (!self::isSafeInt($dataArray[$key] ?? null)) {
                self::fail("{$key} must be an integer");
            }
        }
    }

    private static function isSafeInt(mixed $value): bool
    {
        return is_int($value) && $value >= -self::MAX_SAFE && $value <= self::MAX_SAFE;
    }

    /**
     * @throws WebhookException
     */
    private static function fail(string $reason): never
    {
        throw new WebhookException('Invalid webhook data: ' . $reason);
    }
}

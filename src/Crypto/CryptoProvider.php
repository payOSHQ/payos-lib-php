<?php

namespace PayOS\Crypto;

use RuntimeException;

class CryptoProvider
{
    private const DEFAULT_ALGORITHM = 'sha256';

    /**
     * Create a HMAC signature from an associative object by sorting keys and flattening to query string.
     *
     * @param array|object $data Input data to sign.
     * @param string $key Secret key used for the signature.
     * @return string|null Hex encoded signature or null when input is invalid.
     */
    public function createSignatureFromObj(array|object $data, string $key): ?string
    {
        if ($key === '') {
            return null;
        }

        $normalized = $this->normalizeToAssocArray($data);
        if ($normalized === null || $normalized === []) {
            return null;
        }

        $sorted = $this->sortAssocByKey($normalized);
        $queryString = $this->convertAssocToQueryString($sorted);

        return hash_hmac(self::DEFAULT_ALGORITHM, $queryString, $key);
    }

    /**
     * Create a HMAC signature for payment requests using a fixed field order.
     *
     * @param array|object $data Payment request data containing required fields.
     * @param string $key Secret key used for the signature.
     * @return string|null Hex encoded signature or null when input is invalid.
     */
    public function createSignatureOfPaymentRequest(array|object $data, string $key): ?string
    {
        if ($key === '') {
            return null;
        }

        $normalized = $this->normalizeToAssocArray($data);
        if ($normalized === null) {
            return null;
        }

        $requiredFields = ['amount', 'cancelUrl', 'description', 'orderCode', 'returnUrl'];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $normalized)) {
                return null;
            }
        }

        $dataStr = sprintf(
            'amount=%s&cancelUrl=%s&description=%s&orderCode=%s&returnUrl=%s',
            (string) $normalized['amount'],
            (string) $normalized['cancelUrl'],
            (string) $normalized['description'],
            (string) $normalized['orderCode'],
            (string) $normalized['returnUrl']
        );

        return hash_hmac(self::DEFAULT_ALGORITHM, $dataStr, $key);
    }

    /**
     * Create a HMAC signature from JSON-like data with optional encoding rules.
     *
     * @param string $secretKey Secret key used for the signature.
     * @param array|object|string $jsonData Data to sign. Strings are signed verbatim.
     * @param array $options Optional configuration: encodeUri, sortArrays, algorithm.
     * @return string Hex encoded signature.
     */
    public function createSignature(string $secretKey, array|object|string $jsonData, array $options = []): string
    {
        $algorithm = strtolower($options['algorithm'] ?? self::DEFAULT_ALGORITHM);
        if (!in_array($algorithm, hash_hmac_algos(), true)) {
            throw new RuntimeException(sprintf('Unsupported HMAC algorithm "%s".', $algorithm));
        }

        if (is_string($jsonData)) {
            $payload = $jsonData;
        } else {
            $normalized = $this->normalizeToAssocArray($jsonData) ?? [];
            $sortArrays = (bool) ($options['sortArrays'] ?? false);
            $deepSorted = $this->deepSort($normalized, $sortArrays);
            $encodeUri = $options['encodeUri'] ?? true;
            $payload = $this->buildQueryString($deepSorted, (bool) $encodeUri);
        }

        return hash_hmac($algorithm, $payload, $secretKey);
    }

    /**
     * Generate a RFC 4122 compliant UUID v4 string.
     */
    public function createUuidv4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // Version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // Variant RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function normalizeToAssocArray(array|object $value): ?array
    {
        if (is_array($value)) {
            return $this->convertObjectsToArrays($value);
        }

        // Must be an object at this point
        return $this->convertObjectsToArrays((array) $value);
    }

    private function convertObjectsToArrays(mixed $value): mixed
    {
        if (is_array($value)) {
            $converted = [];
            foreach ($value as $key => $item) {
                $converted[$key] = $this->convertObjectsToArrays($item);
            }

            return $converted;
        }

        if (is_object($value)) {
            return $this->convertObjectsToArrays((array) $value);
        }

        return $value;
    }

    private function sortAssocByKey(array $data): array
    {
        $sorted = $data;
        ksort($sorted);

        return $sorted;
    }

    private function convertAssocToQueryString(array $data): string
    {
        $pairs = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->isAssoc($value)) {
                    $serializedValue = '[object Object]';
                } else {
                    $value = array_map(function ($item) {
                        if (is_array($item)) {
                            return $this->sortAssocByKey($this->convertObjectsToArrays($item));
                        }

                        if (is_object($item)) {
                            return $this->sortAssocByKey($this->convertObjectsToArrays((array) $item));
                        }

                        return $item;
                    }, $value);
                    $serializedValue = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            } elseif (is_object($value)) {
                $serializedValue = '[object Object]';
            } else {
                $normalizedValue = $this->normalizeScalarValue($value);
                $serializedValue = $normalizedValue;
            }

            $pairs[] = sprintf('%s=%s', $key, $serializedValue);
        }

        return implode('&', $pairs);
    }

    private function buildQueryString(array $data, bool $encodeUri): string
    {
        $parts = [];
        foreach ($data as $key => $value) {
            $serializedValue = $this->serializeForQuery($value);
            $encodedKey = $encodeUri ? rawurlencode((string) $key) : (string) $key;
            $encodedValue = $encodeUri ? rawurlencode($serializedValue) : $serializedValue;
            $parts[] = $encodedKey . '=' . $encodedValue;
        }

        return implode('&', $parts);
    }

    private function serializeForQuery(mixed $value): string
    {
        if (is_array($value)) {
            $result = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $result !== false ? $result : '';
        }

        if (is_object($value)) {
            $result = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $result !== false ? $result : '';
        }

        return $this->normalizeScalarValue($value);
    }

    private function normalizeScalarValue(mixed $value): string
    {
        if ($value === null || $value === 'undefined' || $value === 'null') {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function deepSort(mixed $value, bool $sortArrays): mixed
    {
        if (is_array($value)) {
            if ($this->isAssoc($value)) {
                $sorted = $this->sortAssocByKey($value);
                foreach ($sorted as $key => $item) {
                    $sorted[$key] = $this->deepSort($item, $sortArrays);
                }

                return $sorted;
            }

            $result = array_map(fn ($item) => $this->deepSort($item, $sortArrays), $value);
            if ($sortArrays) {
                usort($result, function ($a, $b) {
                    return strcmp($this->stringifyForSorting($a), $this->stringifyForSorting($b));
                });
            }

            return $result;
        }

        if (is_object($value)) {
            return $this->deepSort((array) $value, $sortArrays);
        }

        return $value;
    }

    private function stringifyForSorting(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $normalized = $this->convertObjectsToArrays($value);
            $result = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $result !== false ? $result : '';
        }

        return $this->normalizeScalarValue($value);
    }

    private function isAssoc(array $array): bool
    {
        return !array_is_list($array);
    }
}

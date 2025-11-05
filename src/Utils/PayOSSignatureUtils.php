<?php

namespace PayOS\Utils;

/**
 * PayOSSignatureUtils
 *
 * @package PayOS\Utils
 * @deprecated This class is deprecated and will be removed in a future version. Use the CryptoProvider from the PayOS client instead.
 * @see \PayOS\Crypto\CryptoProvider
 */
class PayOSSignatureUtils
{
    /**
     * createSignatureFromObj
     *
     * @param string $checksumKey
     * @param mixed $obj
     * @return string
     * @deprecated This method is deprecated and will be removed in a future version. Use the CryptoProvider from the PayOS client instead.
     * @see \PayOS\Crypto\CryptoProvider::createSignatureFromObj()
     */
    public static function createSignatureFromObj($checksumKey, $obj)
    {
        ksort($obj);
        $queryStrArr = [];
        foreach ($obj as $key => $value) {
            // if (in_array($value, ["undefined", "null"]) || gettype($value) == "NULL") {
            if ($value === "undefined" || $value === "null" || gettype($value) == "NULL") {
                $value = "";
            }

            if (is_array($value)) {
                $valueSortedElementObj = array_map(function ($ele) {
                    ksort($ele);

                    return $ele;
                }, $value);
                $value = json_encode($valueSortedElementObj, JSON_UNESCAPED_UNICODE);
            }
            $queryStrArr[] = $key . "=" . $value;
        }
        $queryStr = implode("&", $queryStrArr);
        $signature = hash_hmac('sha256', $queryStr, $checksumKey);

        return $signature;
    }

    /**
     * createSignatureOfPaymentRequest
     * @param string $checksumKey
     * @param mixed $obj
     * @return string
     * @deprecated This method is deprecated and will be removed in a future version. Use the CryptoProvider from the PayOS client instead.
     * @see \PayOS\Crypto\CryptoProvider::createSignatureOfPaymentRequest()
     */
    public static function createSignatureOfPaymentRequest($checksumKey, $obj)
    {
        $dataStr = "amount={$obj["amount"]}&cancelUrl={$obj["cancelUrl"]}&description={$obj["description"]}&orderCode={$obj["orderCode"]}&returnUrl={$obj["returnUrl"]}";
        $signature = hash_hmac("sha256", $dataStr, $checksumKey);

        return $signature;
    }
}

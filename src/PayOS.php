<?php

namespace PayOS;

use Exception;
use PayOS\Exceptions\ErrorCode;
use PayOS\Exceptions\ErrorMessage;
use PayOS\Utils\PayOSSignatureUtils;


const PAYOS_BASE_URL = 'https://api-merchant.payos.vn';
/**
 * PayOS
 * 
 * @package PayOS
 */
class PayOS
{
    private string $clientId;
    private string $apiKey;
    private string $checksumKey;
    private ?string $partnerCode;

    /**
     * Create a payOS object to use payment channel methods. Credentials are fields provided after creating a payOS payment channel.
     * 
     * @param string $clientId Client ID of the payOS payment channel
     * @param string $apiKey Api Key of the payOS payment channel
     * @param string $checksumKey Checksum Key of the payOS payment channel
     * @param null|string $partnerCode Your Partner Code
     */
    public function __construct(string $clientId, string $apiKey, string $checksumKey, ?string $partnerCode = null)
    {
        $this->clientId = $clientId;
        $this->apiKey = $apiKey;
        $this->checksumKey = $checksumKey;
        $this->partnerCode = $partnerCode;
    }

    /**
     * Create a payment link for the order data passed in the parameter.
     *
     * @param  array $paymentData Payment data
     * @return array
     * @throws Exception
     */
    public function createPaymentLink(array $paymentData): array
    {
        $orderCode = $paymentData['orderCode'] ?? null;
        $amount = $paymentData['amount'] ?? null;
        $returnUrl = $paymentData['returnUrl'] ?? null;
        $cancelUrl = $paymentData['cancelUrl'] ?? null;
        $description = $paymentData['description'] ?? null;

        if (!($paymentData && $orderCode && $amount && $returnUrl && $cancelUrl && $description)) {
            $requiredPaymentData = [
                'orderCode' => $orderCode,
                'amount' => $amount,
                'returnUrl' => $returnUrl,
                'cancelUrl' => $cancelUrl,
                'description' => $description
            ];
            $requiredKeys = array_keys($requiredPaymentData);
            $keysError = array_filter($requiredKeys, function ($key) use ($requiredPaymentData) {
                return $requiredPaymentData[$key] === null;
            });

            $msgError = ErrorMessage::INVALID_PARAMETER . ' ' . implode(', ', $keysError) . ' must not be null.';
            throw new Exception($msgError, ErrorCode::INVALID_PARAMETER);
        }
        $url = PAYOS_BASE_URL . '/v2/payment-requests';
        $signaturePaymentRequest = PayOSSignatureUtils::createSignatureOfPaymentRequest(
            $this->checksumKey,
            $paymentData
        );

        try {
            $headers = array(
                'x-client-id: ' . $this->clientId,
                'x-api-key: ' . $this->apiKey,
                'Content-Type: application/json'
            );
            if ($this->partnerCode != null) array_push($headers, 'x-partner-code: ' . $this->partnerCode);
            $data = array_merge($paymentData, ['signature' => $signaturePaymentRequest]);

            $paymentRequest = curl_init();
            curl_setopt($paymentRequest, CURLOPT_URL, $url);
            curl_setopt($paymentRequest, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($paymentRequest, CURLOPT_HTTPHEADER, $headers);

            curl_setopt($paymentRequest, CURLOPT_POST, 1);
            curl_setopt($paymentRequest, CURLOPT_POSTFIELDS, json_encode($data));
            $paymentLinkRes = curl_exec($paymentRequest);

            #Ensure to close curl
            curl_close($paymentRequest);
            $paymentLinkRes = json_decode($paymentLinkRes, true);

            if ($paymentLinkRes['code'] == '00') {
                $paymentLinkResSignature = PayOSSignatureUtils::createSignatureFromObj(
                    $this->checksumKey,
                    $paymentLinkRes['data']
                );
                if ($paymentLinkResSignature !== $paymentLinkRes['signature']) {
                    throw new Exception(ErrorMessage::DATA_NOT_INTEGRITY, ErrorCode::DATA_NOT_INTEGRITY);
                }
                if ($paymentLinkRes['data']) {
                    return $paymentLinkRes['data'];
                }
            }
            throw new Exception($paymentLinkRes['desc'], $paymentLinkRes['code']);
        } catch (Exception $error) {
            throw new Exception($error->getMessage(), $error->getCode());
        }
    }

    /**
     * Get payment information of an order that has created a payment link.
     * 
     * @param string|int $orderCode Order code
     * @return array
     * @throws Exception
     */
    public function getPaymentLinkInformation(string|int $orderCode): array
    {
        if (!$orderCode || (is_string($orderCode) && strlen($orderCode) == 0) || (is_int($orderCode) && $orderCode < 0)) {
            throw new Exception(ErrorMessage::INVALID_PARAMETER, ErrorCode::INVALID_PARAMETER);
        }
        $url = PAYOS_BASE_URL . '/v2/payment-requests/' . $orderCode;
        try {
            $headers = array(
                'x-client-id: ' . $this->clientId,
                'x-api-key: ' . $this->apiKey,
                'Content-Type: application/json'
            );

            $paymentRequest = curl_init();
            curl_setopt($paymentRequest, CURLOPT_URL, $url);
            curl_setopt($paymentRequest, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($paymentRequest, CURLOPT_HTTPHEADER, $headers);

            $paymentLinkRes = curl_exec($paymentRequest);

            # Ensure to close curl
            curl_close($paymentRequest);
            $paymentLinkRes = json_decode($paymentLinkRes, true);

            if ($paymentLinkRes['code'] == '00') {
                $paymentLinkResSignature = PayOSSignatureUtils::createSignatureFromObj(
                    $this->checksumKey,
                    $paymentLinkRes['data']
                );
                if ($paymentLinkResSignature !== $paymentLinkRes['signature']) {
                    throw new Exception(ErrorMessage::DATA_NOT_INTEGRITY, ErrorCode::DATA_NOT_INTEGRITY);
                }
                if ($paymentLinkRes['data']) {
                    return $paymentLinkRes['data'];
                }
            }
            throw new Exception($paymentLinkRes['desc'], $paymentLinkRes['code']);
        } catch (Exception $error) {
            throw new Exception($error->getMessage(), $error->getCode());
        }
    }

    /**
     * Validate the Webhook URL of a payment channel and add or update the Webhook URL for that Payment Channel if successful.
     * 
     * @param string $webhookUrl Webhook URL
     * @return string
     * @throws Exception
     */
    public function confirmWebhook(string $webhookUrl): string
    {
        if (!$webhookUrl || strlen($webhookUrl) == 0) {
            throw new Exception(ErrorMessage::INVALID_PARAMETER, ErrorCode::INVALID_PARAMETER);
        }
        $url = PAYOS_BASE_URL . '/confirm-webhook';

        try {
            $headers = array(
                'x-client-id: ' . $this->clientId,
                'x-api-key: ' . $this->apiKey,
                'Content-Type: application/json'
            );

            $data = [
                'webhookUrl' => $webhookUrl
            ];

            $confirmWebhookRequest = curl_init();
            curl_setopt($confirmWebhookRequest, CURLOPT_URL, $url);
            curl_setopt($confirmWebhookRequest, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($confirmWebhookRequest, CURLOPT_HTTPHEADER, $headers);

            curl_setopt($confirmWebhookRequest, CURLOPT_POST, 1);
            curl_setopt($confirmWebhookRequest, CURLOPT_POSTFIELDS, json_encode($data));
            $confirmWebhookRes = curl_exec($confirmWebhookRequest);

            #Ensure to close curl
            curl_close($confirmWebhookRequest);
            $confirmWebhookRes = json_decode($confirmWebhookRes, true);
            $reponseCode = curl_getinfo($confirmWebhookRequest, CURLINFO_HTTP_CODE);

            if ($reponseCode == '400') {
                throw new Exception(ErrorMessage::WEBHOOK_URL_INVALID, ErrorCode::WEBHOOK_URL_INVALID);
            } else if ($reponseCode == '401') {
                throw new Exception(ErrorMessage::UNAUTHORIZED, ErrorCode::UNAUTHORIZED);
            } else if (str_starts_with($reponseCode, '5')) {
                throw new Exception(ErrorMessage::INTERNAL_SERVER_ERROR, ErrorCode::INTERNAL_SERVER_ERROR);
            }
            return $webhookUrl;
        } catch (Exception $error) {
            throw new Exception($error->getMessage(), $error->getCode());
        }
    }

    /**
     * Cancel the payment link of the order.
     * 
     * @param string|int $orderCode Order code
     * @param ?string cancellationReason Reason for cancelling payment link (optional)
     * @return array
     * @throws Exception
     */
    public function cancelPaymentLink(string|int $orderCode, ?string $cancellationReason = null): array
    {
        if (!$orderCode || (is_string($orderCode) && strlen($orderCode) == 0) || (is_int($orderCode) && $orderCode < 0)) {
            throw new Exception(ErrorMessage::INVALID_PARAMETER, ErrorCode::INVALID_PARAMETER);
        }
        $url = PAYOS_BASE_URL . '/v2/payment-requests/' . $orderCode . '/cancel';
        try {
            $headers = array(
                'x-client-id: ' . $this->clientId,
                'x-api-key: ' . $this->apiKey,
                'Content-Type: application/json'
            );
            $data = [
                'cancellationReason' => $cancellationReason
            ];

            $cancelPaymentLinkRequest = curl_init();
            curl_setopt($cancelPaymentLinkRequest, CURLOPT_URL, $url);
            curl_setopt($cancelPaymentLinkRequest, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($cancelPaymentLinkRequest, CURLOPT_HTTPHEADER, $headers);

            curl_setopt($cancelPaymentLinkRequest, CURLOPT_POST, 1);
            curl_setopt($cancelPaymentLinkRequest, CURLOPT_POSTFIELDS, json_encode($data));
            $cancelPaymentLinkRes = curl_exec($cancelPaymentLinkRequest);

            #Ensure to close curl
            curl_close($cancelPaymentLinkRequest);
            $cancelPaymentLinkRes = json_decode($cancelPaymentLinkRes, true);

            if ($cancelPaymentLinkRes['code'] == '00') {
                $cancelPaymentLinkResSignature = PayOSSignatureUtils::createSignatureFromObj(
                    $this->checksumKey,
                    $cancelPaymentLinkRes['data']
                );
                if ($cancelPaymentLinkResSignature !== $cancelPaymentLinkRes['signature']) {
                    throw new Exception(ErrorMessage::DATA_NOT_INTEGRITY, ErrorCode::DATA_NOT_INTEGRITY);
                }
                if ($cancelPaymentLinkRes['data']) {
                    return $cancelPaymentLinkRes['data'];
                }
            }
            throw new Exception($cancelPaymentLinkRes['desc'], $cancelPaymentLinkRes['code']);
        } catch (Exception $error) {
            throw new Exception($error->getMessage(), $error->getCode());
        }
    }

    /**
     * Verify data received via webhook after payment.
     * 
     * @param array $webhookBody Request body received from webhook
     * @return array
     * @throws Exception
     */
    public function verifyPaymentWebhookData(array $webhookBody): array
    {
        if (!$webhookBody || count($webhookBody) == 0) {
            throw new Exception(ErrorMessage::NO_DATA, ErrorCode::NO_DATA);
        }
        $signature = $webhookBody['signature'] ?? null;
        $data = $webhookBody['data'] ?? null;

        if (!$signature) {
            throw new Exception(ErrorMessage::NO_SIGNATURE, ErrorCode::NO_SIGNATURE);
        }
        if (!$data) {
            throw new Exception(ErrorMessage::NO_DATA, ErrorCode::NO_DATA);
        }
        $signatureData = PayOSSignatureUtils::createSignatureFromObj($this->checksumKey, $data);
        if ($signatureData !== $signature) {
            throw new Exception(ErrorMessage::DATA_NOT_INTEGRITY, ErrorCode::DATA_NOT_INTEGRITY);
        }
        return $data;
    }
}

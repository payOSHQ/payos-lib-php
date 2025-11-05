<?php

namespace PayOS\Resources\Webhooks;

use PayOS\Core\APIResource;
use PayOS\Core\ObjectSerializer;
use PayOS\Exceptions\PayOSException;
use PayOS\Exceptions\WebhookException;
use PayOS\Models\Webhooks\ConfirmWebhookRequest;
use PayOS\Models\Webhooks\ConfirmWebhookResponse;
use PayOS\Models\Webhooks\Webhook;
use PayOS\Models\Webhooks\WebhookData;

/**
 * Webhooks Resource
 */
class Webhooks extends APIResource
{
    /**
     * Validate and register a webhook URL with PayOS.
     * PayOS will test the webhook endpoint by sending a validation request to it.
     * If the webhook responds correctly, it will be registered for payment notifications.
     *
     * @param string $webhookUrl Your webhook endpoint URL that will receive payment notifications
     * @param array|null $options Additional options (headers, max retries, as array, etc.)
     * @return ConfirmWebhookResponse|array The confirmed webhook URL result
     * @throws WebhookException When webhook URL is invalid or validation fails
     * @throws PayOSException
     */
    public function confirm(string $webhookUrl, ?array $options = null): ConfirmWebhookResponse|array
    {
        if (empty($webhookUrl)) {
            throw new WebhookException('Webhook URL invalid.');
        }

        try {
            $requestData = new ConfirmWebhookRequest($webhookUrl);
            $body = ObjectSerializer::toArray($requestData);

            $requestOptions = array_merge($options ?? [], [
                'body' => $body,
            ]);

            $data = $this->_client->post('/confirm-webhook', $requestOptions);

            if (isset($options['asArray']) && $options['asArray']) {
                return $data;
            }

            $result = ObjectSerializer::fromArray($data, ConfirmWebhookResponse::class);
            if ($result === null) {
                throw new PayOSException('Failed to deserialize response');
            }

            return $result;
        } catch (PayOSException $error) {
            // The error comes from PayOS API when validating the webhook URL
            // PayOS tests the webhook endpoint and reports back the validation result
            throw new WebhookException('Webhook validation failed: ' . $error->getMessage());
        }
    }

    /**
     * Verify the webhook data sent from PayOS.
     *
     * @param Webhook|array $webhook The webhook data received from PayOS (can be Webhook object or array)
     * @param array|null $options Additional options (asArray)
     * @return WebhookData|array The verified webhook data
     * @throws WebhookException When webhook data or signature is invalid
     */
    public function verify($webhook, ?array $options = null): WebhookData|array
    {
        // Convert array to Webhook object if needed
        if (is_array($webhook)) {
            $webhook = ObjectSerializer::fromArray($webhook, Webhook::class);
            if ($webhook === null) {
                throw new WebhookException('Invalid webhook data format');
            }
        }

        if (!($webhook instanceof Webhook)) {
            throw new WebhookException('Invalid webhook data');
        }

        if (!isset($webhook->data)) {
            throw new WebhookException('Invalid webhook data');
        }

        if (empty($webhook->signature)) {
            throw new WebhookException('Invalid signature');
        }

        // Get crypto provider from client
        $crypto = $this->_client->getCrypto();
        $checksumKey = $this->_client->getChecksumKey();

        // Convert WebhookData to array for signature verification
        $dataArray = ObjectSerializer::toArray($webhook->data);

        // Ensure we have an array for signature verification
        if (!is_array($dataArray)) {
            throw new WebhookException('Invalid webhook data format');
        }

        $signedSignature = $crypto->createSignatureFromObj($dataArray, $checksumKey);

        if (empty($signedSignature) || $signedSignature !== $webhook->signature) {
            throw new WebhookException('Data not integrity');
        }

        if (isset($options['asArray']) && $options['asArray']) {
            return $dataArray;
        }

        return $webhook->data;
    }
}

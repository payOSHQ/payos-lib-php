<?php

namespace PayOS\Resources\V2\PaymentRequests;

use PayOS\Core\APIResource;
use PayOS\Core\ObjectSerializer;
use PayOS\Models\V2\PaymentRequests\CancelPaymentLinkRequest;
use PayOS\Models\V2\PaymentRequests\CreatePaymentLinkRequest;
use PayOS\Models\V2\PaymentRequests\CreatePaymentLinkResponse;
use PayOS\Models\V2\PaymentRequests\PaymentLink;
use PayOS\Resources\V2\PaymentRequests\Invoices\Invoices;

/**
 * Payment Requests
 */
class PaymentRequests extends APIResource
{
    /**
     * Creates a payment link for the provided order data.
     *
     * @param CreatePaymentLinkRequest|array $paymentData The details of payment link to be created.
     * @param array|null $options Additional options (headers, max retries, as array, etc.)
     * @return CreatePaymentLinkResponse|array The created payment link details
     * @throws \PayOS\Exceptions\APIException
     */
    public function create(
        CreatePaymentLinkRequest|array $paymentData,
        ?array $options = null
    ): CreatePaymentLinkResponse|array {
        $body = is_array($paymentData) ? $paymentData : ObjectSerializer::toArray($paymentData);

        $requestOptions = array_merge($options ?? [], [
            'body' => $body,
            'signatureOpts' => [
                'request' => 'create-payment-link',
                'response' => 'body',
            ],
        ]);

        $data = $this->_client->post('/v2/payment-requests', $requestOptions);

        if (isset($options['asArray']) && $options['asArray']) {
            return $data;
        }

        $result = ObjectSerializer::fromArray($data, CreatePaymentLinkResponse::class);
        if ($result === null) {
            throw new \PayOS\Exceptions\APIException(500, null, 'Failed to deserialize response', []);
        }

        return $result;
    }

    /**
     * Retrieve payment link information from payment link ID or order code.
     *
     * @param string|int $id The payment link ID (string) or order code (int)
     * @param array|null $options Additional options (headers, max retries, as array, etc.)
     * @return PaymentLink|array The payment link details
     * @throws \PayOS\Exceptions\APIException
     */
    public function get($id, ?array $options = null): PaymentLink|array
    {
        $requestOptions = array_merge($options ?? [], [
            'signatureOpts' => [
                'response' => 'body',
            ],
        ]);

        $data = $this->_client->get("/v2/payment-requests/{$id}", $requestOptions);

        if (isset($options['asArray']) && $options['asArray']) {
            return $data;
        }

        $result = ObjectSerializer::fromArray($data, PaymentLink::class);
        if ($result === null) {
            throw new \PayOS\Exceptions\APIException(500, null, 'Failed to deserialize response', []);
        }

        return $result;
    }

    /**
     * Cancel a payment link by payment link ID or order code.
     *
     * @param string|int $id The payment link ID (string) or order code (int)
     * @param string|null $cancellationReason The cancellation reason
     * @param array|null $options Additional options (headers, max retries, as array, etc.)
     * @return PaymentLink|array The cancelled payment link details
     * @throws \PayOS\Exceptions\APIException
     */
    public function cancel(
        $id,
        ?string $cancellationReason = null,
        ?array $options = null
    ): PaymentLink|array {
        $requestData = null;
        if ($cancellationReason !== null) {
            $cancelRequest = new CancelPaymentLinkRequest($cancellationReason);
            $requestData = ObjectSerializer::toArray($cancelRequest);
        }

        $requestOptions = array_merge($options ?? [], [
            'body' => $requestData,
            'signatureOpts' => [
                'response' => 'body',
            ],
        ]);

        $data = $this->_client->post("/v2/payment-requests/{$id}/cancel", $requestOptions);

        if (isset($options['asArray']) && $options['asArray']) {
            return $data;
        }

        $result = ObjectSerializer::fromArray($data, PaymentLink::class);
        if ($result === null) {
            throw new \PayOS\Exceptions\APIException(500, null, 'Failed to deserialize response', []);
        }

        return $result;
    }

    /**
     * Access to invoice operations
     *
     * @var Invoices
     */
    public Invoices $invoices;

    /**
     * Constructor - initializes sub-resources
     *
     * @param \PayOS\PayOS $client
     */
    public function __construct(\PayOS\PayOS $client)
    {
        parent::__construct($client);
        $this->invoices = new Invoices($client);
    }
}

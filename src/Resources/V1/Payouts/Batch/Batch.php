<?php

namespace PayOS\Resources\V1\Payouts\Batch;

use PayOS\Core\APIResource;
use PayOS\Core\ObjectSerializer;
use PayOS\Models\V1\Payouts\Batch\PayoutBatchRequest;
use PayOS\Models\V1\Payouts\Payout;

/**
 * Batch Payouts Resource
 */
class Batch extends APIResource
{
    /**
     * Create a batch payout.
     *
     * @param PayoutBatchRequest|array $payoutData The details of batch payout to be created
     * @param string|null $idempotencyKey A unique key for ensuring idempotency
     * @param array|null $options Additional options (headers, max retries, as array, etc.)
     * @return Payout|array A promise that resolves to the newly created Payout object
     * @throws \PayOS\Exceptions\APIException
     */
    public function create(
        PayoutBatchRequest|array $payoutData,
        ?string $idempotencyKey = null,
        ?array $options = null
    ): Payout|array {
        // Generate idempotency key if not provided
        if ($idempotencyKey === null) {
            $idempotencyKey = $this->_client->getCrypto()->createUuidv4();
        }

        $body = is_array($payoutData) ? $payoutData : ObjectSerializer::toArray($payoutData);

        $requestOptions = array_merge($options ?? [], [
            'body' => $body,
            'signatureOpts' => [
                'request' => 'header',
                'response' => 'header',
            ],
            'headers' => [
                'x-idempotency-key' => $idempotencyKey,
            ],
        ]);

        $data = $this->_client->post('/v1/payouts/batch', $requestOptions);

        if (isset($options['asArray']) && $options['asArray']) {
            return $data;
        }

        $result = ObjectSerializer::fromArray($data, Payout::class);
        if ($result === null) {
            throw new \PayOS\Exceptions\APIException(500, null, 'Failed to deserialize response', []);
        }

        return $result;
    }
}

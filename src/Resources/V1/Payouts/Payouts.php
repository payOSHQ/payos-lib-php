<?php

namespace PayOS\Resources\V1\Payouts;

use PayOS\Core\APIResource;
use PayOS\Core\GenericPage;
use PayOS\Core\HTTPMethod;
use PayOS\Core\ObjectSerializer;
use PayOS\Models\V1\Payouts\Batch\PayoutBatchRequest;
use PayOS\Models\V1\Payouts\EstimateCredit;
use PayOS\Models\V1\Payouts\GetPayoutListParam;
use PayOS\Models\V1\Payouts\Payout;
use PayOS\Models\V1\Payouts\PayoutRequest;
use PayOS\Resources\V1\Payouts\Batch\Batch;

/**
 * Payouts Resource
 */
class Payouts extends APIResource
{
    /**
     * @var Batch Batch payouts sub-resource
     */
    public Batch $batch;

    /**
     * Constructor
     */
    public function __construct(\PayOS\PayOS $client)
    {
        parent::__construct($client);
        $this->batch = new Batch($client);
    }

    /**
     * Create a new payout.
     *
     * @param PayoutRequest|array $payoutData The details of the payout to be created
     * @param string|null $idempotencyKey A unique key for ensuring idempotency
     * @param array|null $options Additional options (headers, max retries, as array, etc.)
     * @return Payout|array A promise that resolves to the newly created Payout object
     * @throws \PayOS\Exceptions\APIException
     */
    public function create(
        PayoutRequest|array $payoutData,
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

        $data = $this->_client->post('/v1/payouts/', $requestOptions);

        if (isset($options['asArray']) && $options['asArray']) {
            return $data;
        }

        $result = ObjectSerializer::fromArray($data, Payout::class);
        if ($result === null) {
            throw new \PayOS\Exceptions\APIException(500, null, 'Failed to deserialize response', []);
        }

        return $result;
    }

    /**
     * Retrieves detailed information about a specific payout.
     *
     * @param string $payoutId The unique identifier of the payout to retrieve
     * @param array|null $options Additional options (headers, max retries, as array, etc.)
     * @return Payout|array A Payout object containing the payout's details
     * @throws \PayOS\Exceptions\APIException
     */
    public function get(string $payoutId, ?array $options = null): Payout|array
    {
        $requestOptions = array_merge($options ?? [], [
            'signatureOpts' => [
                'response' => 'header',
            ],
        ]);

        $data = $this->_client->get("/v1/payouts/{$payoutId}", $requestOptions);

        if (isset($options['asArray']) && $options['asArray']) {
            return $data;
        }

        $result = ObjectSerializer::fromArray($data, Payout::class);
        if ($result === null) {
            throw new \PayOS\Exceptions\APIException(500, null, 'Failed to deserialize response', []);
        }

        return $result;
    }

    /**
     * Estimate credit required for one or multiple payouts.
     *
     * @param PayoutRequest|PayoutBatchRequest|array $payoutData The payout details
     * @param array|null $options Additional options (headers, max retries, as array, etc.)
     * @return EstimateCredit|array An object containing the estimated credit required
     * @throws \PayOS\Exceptions\APIException
     */
    public function estimateCredit(
        PayoutRequest|PayoutBatchRequest|array $payoutData,
        ?array $options = null
    ): EstimateCredit|array {
        $body = is_array($payoutData) ? $payoutData : ObjectSerializer::toArray($payoutData);

        $requestOptions = array_merge($options ?? [], [
            'body' => $body,
            'signatureOpts' => [
                'request' => 'header',
            ],
        ]);

        $data = $this->_client->post('/v1/payouts/estimate-credit', $requestOptions);

        if (isset($options['asArray']) && $options['asArray']) {
            return $data;
        }

        $result = ObjectSerializer::fromArray($data, EstimateCredit::class);
        if ($result === null) {
            throw new \PayOS\Exceptions\APIException(500, null, 'Failed to deserialize response', []);
        }

        return $result;
    }

    /**
     * Retrieves a paginated list of payouts filtered by the given criteria.
     *
     * @param GetPayoutListParam|array|null $params The filtering options including pagination parameters
     * @param array|null $options Additional options (headers, max retries, as array, etc.)
     * @return GenericPage<Payout> A page of payouts matching the specified criteria
     * @throws \PayOS\Exceptions\APIException
     */
    public function list(
        GetPayoutListParam|array|null $params = null,
        ?array $options = null
    ): GenericPage {
        // Default parameters
        if ($params === null) {
            $params = new GetPayoutListParam(limit: 10, offset: 0);
        }

        // If params is an array, convert to object
        if (is_array($params)) {
            $params = ObjectSerializer::fromArray($params, GetPayoutListParam::class);
        }

        // Convert parameters to array
        $processedParams = [];
        if (isset($params->referenceId)) {
            $processedParams['referenceId'] = $params->referenceId;
        }
        if (isset($params->approvalState)) {
            $processedParams['approvalState'] = $params->approvalState->value;
        }
        if (isset($params->category) && count($params->category) > 0) {
            $processedParams['category'] = implode(',', $params->category);
        }
        if (isset($params->fromDate)) {
            $processedParams['fromDate'] = $params->fromDate->format(\DateTime::ATOM);
        }
        if (isset($params->toDate)) {
            $processedParams['toDate'] = $params->toDate->format(\DateTime::ATOM);
        }
        if (isset($params->limit)) {
            $processedParams['limit'] = $params->limit;
        }
        if (isset($params->offset)) {
            $processedParams['offset'] = $params->offset;
        }

        $requestOptions = array_merge($options ?? [], [
            'method' => 'GET',
            'path' => '/v1/payouts',
            'query' => $processedParams,
            'signatureOpts' => [
                'response' => 'header',
            ],
        ]);

        $data = $this->_client->request(HTTPMethod::GET, '/v1/payouts', $requestOptions);

        return new GenericPage($this->_client, $data, $requestOptions, Payout::class);
    }
}

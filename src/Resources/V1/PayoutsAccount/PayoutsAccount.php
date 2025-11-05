<?php

namespace PayOS\Resources\V1\PayoutsAccount;

use PayOS\Core\APIResource;
use PayOS\Core\ObjectSerializer;
use PayOS\Models\V1\PayoutsAccount\PayoutAccountInfo;

/**
 * Payouts Account Resource
 */
class PayoutsAccount extends APIResource
{
    /**
     * Retrieves the current payout account balance.
     *
     * @param array|null $options Additional options (headers, max retries, as array, etc.)
     * @return PayoutAccountInfo|array A PayoutAccountInfo object containing the current balance and related account details
     * @throws \PayOS\Exceptions\APIException
     */
    public function balance(?array $options = null): PayoutAccountInfo|array
    {
        $requestOptions = array_merge($options ?? [], [
            'signatureOpts' => [
                'response' => 'header',
            ],
        ]);

        $data = $this->_client->get('/v1/payouts-account/balance', $requestOptions);

        if (isset($options['asArray']) && $options['asArray']) {
            return $data;
        }

        $result = ObjectSerializer::fromArray($data, PayoutAccountInfo::class);
        if ($result === null) {
            throw new \PayOS\Exceptions\APIException(500, null, 'Failed to deserialize response', []);
        }

        return $result;
    }
}

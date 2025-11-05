<?php

namespace PayOS\Core;

use PayOS\PayOS;

/**
 * Base class for all API resources
 *
 * Provides access to the PayOS client instance for making API calls
 */
abstract class APIResource
{
    /**
     * @var PayOS The PayOS client instance
     */
    protected PayOS $_client;

    /**
     * Create a new API resource instance
     *
     * @param PayOS $client The PayOS client instance
     */
    public function __construct(PayOS $client)
    {
        $this->_client = $client;
    }

    /**
     * Get the PayOS client instance
     *
     * @return PayOS
     */
    protected function getClient(): PayOS
    {
        return $this->_client;
    }
}

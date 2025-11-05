<?php

namespace PayOS\Core;

use PayOS\PayOS;

/**
 * Generic paginated response class that can be used for any item type
 *
 * @template TItem
 * @extends Page<TItem>
 */
class GenericPage extends Page
{
    /**
     * @param PayOS $client
     * @param array $data
     * @param array $options
     * @return GenericPage<TItem>
     */
    protected function createPageInstance(PayOS $client, array $data, array $options): GenericPage
    {
        return new GenericPage($client, $data, $options, $this->_itemClass);
    }
}

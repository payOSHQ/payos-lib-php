<?php

namespace PayOS\Core;

use ArrayIterator;
use IteratorAggregate;
use PayOS\PayOS;
use Traversable;

/**
 * Abstract base class for paginated API responses.
 * Provides both iteration and manual pagination methods.
 *
 * @template TItem
 * @implements IteratorAggregate<int, TItem>
 */
abstract class Page implements IteratorAggregate
{
    /**
     * @var PayOS The PayOS client instance
     */
    protected PayOS $_client;

    /**
     * @var array<TItem> The items in the current page
     */
    protected array $_data;

    /**
     * @var Pagination Pagination metadata
     */
    protected Pagination $_pagination;

    /**
     * @var array The request options used to fetch this page
     */
    protected array $_options;

    /**
     * @var string|null The class name for deserializing items
     */
    protected ?string $_itemClass;

    /**
     * Constructor
     *
     * @param PayOS $client The PayOS client instance
     * @param array $data The response data
     * @param array $options The request options
     * @param string|null $itemClass The class name for deserializing items
     */
    public function __construct(PayOS $client, array $data, array $options, ?string $itemClass = null)
    {
        $this->_client = $client;
        $this->_options = $options;
        $this->_itemClass = $itemClass;

        if (isset($data['pagination'])) {
            $paginationData = $data['pagination'];
            $this->_pagination = new Pagination(
                $paginationData['limit'] ?? 0,
                $paginationData['offset'] ?? 0,
                $paginationData['total'] ?? 0,
                $paginationData['count'] ?? 0,
                $paginationData['hasMore'] ?? false
            );

            // Get the first array property from data (could be 'payouts', 'items', etc.)
            $dataKeys = array_filter(array_keys($data), fn ($key) => $key !== 'pagination');
            $dataKeys = array_values($dataKeys); // Re-index array
            $rawData = count($dataKeys) > 0 ? ($data[$dataKeys[0]] ?? []) : [];

            // Deserialize items if a class name is provided
            if ($this->_itemClass !== null && class_exists($this->_itemClass)) {
                $this->_data = [];
                foreach ($rawData as $item) {
                    $deserialized = ObjectSerializer::fromArray($item, $this->_itemClass);
                    if ($deserialized !== null) {
                        $this->_data[] = $deserialized;
                    }
                }
            } else {
                $this->_data = $rawData;
            }
        } else {
            $this->_data = [];
            $this->_pagination = new Pagination(0, 0, 0, 0, false);
        }
    }

    /**
     * Get the items in the current page
     *
     * @return array<TItem>
     */
    public function getData(): array
    {
        return $this->_data;
    }

    /**
     * Get pagination information for the current page
     *
     * @return Pagination
     */
    public function getPagination(): Pagination
    {
        return $this->_pagination;
    }

    /**
     * Check if there are more pages available
     *
     * @return bool
     */
    public function hasNextPage(): bool
    {
        return $this->_pagination->hasMore;
    }

    /**
     * Get the next page of results
     *
     * @return static
     * @throws \Exception If no more pages are available
     */
    public function getNextPage()
    {
        if (!$this->hasNextPage()) {
            throw new \Exception('No more pages available');
        }

        $nextOffset = $this->_pagination->offset + $this->_pagination->count;
        $nextOptions = $this->_options;
        $nextOptions['query'] = array_merge(
            $nextOptions['query'] ?? [],
            [
                'offset' => $nextOffset,
                'limit' => $this->_pagination->limit,
            ]
        );

        $response = $this->_client->request(
            HTTPMethod::from($nextOptions['method'] ?? 'GET'),
            $nextOptions['path'] ?? '',
            $nextOptions
        );

        return $this->createPageInstance($this->_client, $response, $nextOptions);
    }

    /**
     * Check if there are previous pages available
     *
     * @return bool
     */
    public function hasPreviousPage(): bool
    {
        return $this->_pagination->offset > 0;
    }

    /**
     * Get the previous page of results
     *
     * @return static
     * @throws \Exception If no previous pages are available
     */
    public function getPreviousPage()
    {
        if (!$this->hasPreviousPage()) {
            throw new \Exception('No previous pages available');
        }

        $prevOffset = max(0, $this->_pagination->offset - $this->_pagination->limit);
        $prevOptions = $this->_options;
        $prevOptions['query'] = array_merge(
            $prevOptions['query'] ?? [],
            [
                'offset' => $prevOffset,
                'limit' => $this->_pagination->limit,
            ]
        );

        $response = $this->_client->request(
            HTTPMethod::from($prevOptions['method'] ?? 'GET'),
            $prevOptions['path'] ?? '',
            $prevOptions
        );

        return $this->createPageInstance($this->_client, $response, $prevOptions);
    }

    /**
     * Abstract method to create a new page instance
     * Must be implemented by subclasses
     *
     * @param PayOS $client
     * @param array $data
     * @param array $options
     * @return static
     */
    abstract protected function createPageInstance(PayOS $client, array $data, array $options);

    /**
     * Get an iterator for the current page items
     *
     * @return Traversable<int, TItem>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->_data);
    }

    /**
     * Collect all items from all pages into an array
     *
     * @return array<TItem>
     */
    public function toArray(): array
    {
        $items = [];

        // Add current page items
        foreach ($this->_data as $item) {
            $items[] = $item;
        }

        // Fetch and add items from subsequent pages
        $currentPage = $this;
        while ($currentPage->hasNextPage()) {
            $currentPage = $currentPage->getNextPage();
            foreach ($currentPage->getData() as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }
}

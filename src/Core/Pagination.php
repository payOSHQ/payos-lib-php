<?php

namespace PayOS\Core;

/**
 * Pagination metadata
 */
class Pagination
{
    /**
     * @var int Number of items per page
     */
    public int $limit;

    /**
     * @var int Starting offset for the current page
     */
    public int $offset;

    /**
     * @var int Total number of items across all pages
     */
    public int $total;

    /**
     * @var int Number of items in the current page
     */
    public int $count;

    /**
     * @var bool Whether there are more pages available
     */
    public bool $hasMore;

    /**
     * Constructor
     *
     * @param int $limit Number of items per page
     * @param int $offset Starting offset for the current page
     * @param int $total Total number of items across all pages
     * @param int $count Number of items in the current page
     * @param bool $hasMore Whether there are more pages available
     */
    public function __construct(
        int $limit,
        int $offset,
        int $total,
        int $count,
        bool $hasMore
    ) {
        $this->limit = $limit;
        $this->offset = $offset;
        $this->total = $total;
        $this->count = $count;
        $this->hasMore = $hasMore;
    }
}

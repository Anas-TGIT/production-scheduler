<?php

namespace App\Domain\Scheduling\DTOs;

/**
 * Result of mapping stored orders: the orders the engine can schedule, and the ones it had to leave out.
 */
final readonly class MappedOrders
{
    /**
     * @param  list<SchedulingOrder>  $orders
     * @param  array<int, string>  $skipped  order ID => reason
     */
    public function __construct(
        public array $orders,
        public array $skipped,
    ) {}
}

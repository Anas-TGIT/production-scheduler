<?php

namespace App\Domain\Scheduling\DTOs;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Persistence-free view of an order, as the scheduling engine needs it.
 */
final readonly class SchedulingOrder
{
    public function __construct(
        public int $orderId,
        public string $customerName,
        public int $productTypeId,
        public string $productTypeName,
        public int $totalQuantity,
        public int $productionMinutes,
        public CarbonImmutable $dueAt,
    ) {
        if ($productionMinutes <= 0) {
            throw new InvalidArgumentException(
                "Order #{$orderId}: production duration must be positive, got {$productionMinutes}."
            );
        }
    }
}

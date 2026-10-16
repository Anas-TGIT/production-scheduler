<?php

namespace App\Domain\Scheduling\Strategies;

use App\Domain\Scheduling\Contracts\SchedulingStrategy;
use App\Domain\Scheduling\DTOs\SchedulingOrder;
use Carbon\CarbonImmutable;

/**
 * Baseline: earliest deadline first, ties broken by order ID. O(n log n).
 * Simple and predictable; it ignores changeovers entirely, so it is not optimal here.
 */
final class EarliestDueDateStrategy implements SchedulingStrategy
{
    public const KEY = 'edd';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Earliest Due Date (baseline)';
    }

    public function sequence(array $orders, CarbonImmutable $startAt): array
    {
        $sorted = array_values($orders);

        usort($sorted, static fn (SchedulingOrder $a, SchedulingOrder $b): int => [$a->dueAt->getTimestamp(), $a->orderId]
            <=> [$b->dueAt->getTimestamp(), $b->orderId]);

        return $sorted;
    }
}

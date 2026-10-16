<?php

namespace App\Domain\Scheduling\Contracts;

use App\Domain\Scheduling\DTOs\SchedulingOrder;
use Carbon\CarbonImmutable;

/**
 * Chooses the production sequence. Timing is NOT computed here (see TimelineSimulator).
 */
interface SchedulingStrategy
{
    public function key(): string;

    public function label(): string;

    /**
     * @param  list<SchedulingOrder>  $orders
     * @return list<SchedulingOrder>
     */
    public function sequence(array $orders, CarbonImmutable $startAt): array;
}

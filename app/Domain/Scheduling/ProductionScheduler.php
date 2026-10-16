<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\Contracts\SchedulingStrategy;
use App\Domain\Scheduling\DTOs\ScheduleResult;
use App\Domain\Scheduling\DTOs\SchedulingOrder;
use Carbon\CarbonImmutable;

/**
 * Entry point of the engine: strategy picks the order, simulator computes the times.
 * Pure PHP: no database or framework access.
 */
final class ProductionScheduler
{
    public function __construct(
        private readonly SchedulingStrategy $strategy,
        private readonly TimelineSimulator $simulator,
    ) {}

    /**
     * @param  list<SchedulingOrder>  $orders
     */
    public function generate(array $orders, CarbonImmutable $startAt): ScheduleResult
    {
        return $this->simulator->simulate($this->strategy->sequence($orders, $startAt), $startAt);
    }
}

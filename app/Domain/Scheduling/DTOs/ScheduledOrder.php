<?php

namespace App\Domain\Scheduling\DTOs;

use Carbon\CarbonImmutable;

/**
 * One order placed on the production timeline.
 */
final readonly class ScheduledOrder
{
    public function __construct(
        public int $position,
        public SchedulingOrder $order,
        public int $setupMinutes,
        public CarbonImmutable $setupStartAt,
        public CarbonImmutable $productionStartAt,
        public CarbonImmutable $finishAt,
        /** Signed: negative means finished early. */
        public int $latenessMinutes,
        /** max(0, lateness). */
        public int $tardinessMinutes,
    ) {}

    public function isLate(): bool
    {
        return $this->tardinessMinutes > 0;
    }

    public function productionMinutes(): int
    {
        return $this->order->productionMinutes;
    }
}

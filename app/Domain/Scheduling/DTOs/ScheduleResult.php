<?php

namespace App\Domain\Scheduling\DTOs;

use Carbon\CarbonImmutable;

final readonly class ScheduleResult
{
    /**
     * @param  list<ScheduledOrder>  $entries
     */
    public function __construct(
        public CarbonImmutable $startAt,
        public CarbonImmutable $endAt,
        public array $entries,
        public int $totalProductionMinutes,
        public int $totalSetupMinutes,
        public int $changeoverCount,
        public int $lateOrderCount,
        public int $totalTardinessMinutes,
    ) {}

    public function makespanMinutes(): int
    {
        return $this->totalProductionMinutes + $this->totalSetupMinutes;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /** @return list<int> */
    public function orderIds(): array
    {
        return array_map(static fn (ScheduledOrder $entry): int => $entry->order->orderId, $this->entries);
    }

    /**
     * Lexicographic quality key, lower is better:
     * late orders, then total tardiness, then setup time, then makespan.
     *
     * @return array{int, int, int, int}
     */
    public function qualityKey(): array
    {
        return [
            $this->lateOrderCount,
            $this->totalTardinessMinutes,
            $this->totalSetupMinutes,
            $this->makespanMinutes(),
        ];
    }

    public function isBetterThan(self $other): bool
    {
        return $this->qualityKey() < $other->qualityKey();
    }
}

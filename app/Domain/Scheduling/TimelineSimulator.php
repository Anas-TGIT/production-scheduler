<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\DTOs\ScheduledOrder;
use App\Domain\Scheduling\DTOs\ScheduleResult;
use App\Domain\Scheduling\DTOs\SchedulingOrder;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Places an already-chosen sequence on a single, continuously running production line.
 * The only place where timing rules live: every strategy evaluates sequences through it.
 */
final class TimelineSimulator
{
    public function __construct(private readonly int $changeoverMinutes)
    {
        if ($changeoverMinutes < 0) {
            throw new InvalidArgumentException("Changeover minutes cannot be negative, got {$changeoverMinutes}.");
        }
    }

    public function changeoverMinutes(): int
    {
        return $this->changeoverMinutes;
    }

    /**
     * @param  list<SchedulingOrder>  $sequence  orders in production order
     */
    public function simulate(array $sequence, CarbonImmutable $startAt): ScheduleResult
    {
        $cursor = $startAt;
        $previousTypeId = null;
        $entries = [];
        $productionTotal = $setupTotal = $changeovers = $lateCount = $tardinessTotal = 0;

        foreach (array_values($sequence) as $index => $order) {
            $setupMinutes = $this->setupMinutesBetween($previousTypeId, $order->productTypeId);
            $productionStartAt = $cursor->addMinutes($setupMinutes);
            $finishAt = $productionStartAt->addMinutes($order->productionMinutes);
            $latenessMinutes = $this->minutesBetween($order->dueAt, $finishAt);
            $tardinessMinutes = max(0, $latenessMinutes);

            $entries[] = new ScheduledOrder(
                position: $index + 1,
                order: $order,
                setupMinutes: $setupMinutes,
                setupStartAt: $cursor,
                productionStartAt: $productionStartAt,
                finishAt: $finishAt,
                latenessMinutes: $latenessMinutes,
                tardinessMinutes: $tardinessMinutes,
            );

            $productionTotal += $order->productionMinutes;
            $setupTotal += $setupMinutes;
            // A changeover is a product-type switch, even when the configured delay is 0 minutes.
            $changeovers += $previousTypeId !== null && $previousTypeId !== $order->productTypeId ? 1 : 0;
            $lateCount += $tardinessMinutes > 0 ? 1 : 0;
            $tardinessTotal += $tardinessMinutes;

            $cursor = $finishAt;
            $previousTypeId = $order->productTypeId;
        }

        return new ScheduleResult(
            startAt: $startAt,
            endAt: $cursor,
            entries: $entries,
            totalProductionMinutes: $productionTotal,
            totalSetupMinutes: $setupTotal,
            changeoverCount: $changeovers,
            lateOrderCount: $lateCount,
            totalTardinessMinutes: $tardinessTotal,
        );
    }

    /** No setup before the first order (initial machine state is unknown) or between same-type orders. */
    private function setupMinutesBetween(?int $previousTypeId, int $nextTypeId): int
    {
        return $previousTypeId === null || $previousTypeId === $nextTypeId ? 0 : $this->changeoverMinutes;
    }

    /**
     * Signed whole minutes from $from to $to. Positive (late) values are rounded up so that
     * finishing even a few seconds after the deadline counts as late; finishing exactly on it does not.
     */
    private function minutesBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $seconds = $to->getTimestamp() - $from->getTimestamp();

        return $seconds > 0 ? intdiv($seconds + 59, 60) : -intdiv(-$seconds, 60);
    }
}

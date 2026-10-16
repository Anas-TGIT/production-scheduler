<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\DTOs\SchedulingOrder;
use App\Domain\Scheduling\Strategies\EarliestDueDateStrategy;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EarliestDueDateStrategyTest extends TestCase
{
    use BuildsSchedulingOrders;

    #[Test]
    public function it_orders_by_deadline_then_by_id(): void
    {
        $orders = [
            $this->order(4, 1, 100, '2026-01-05 15:00'),
            $this->order(3, 2, 100, '2026-01-05 12:00'),
            $this->order(1, 3, 100, '2026-01-05 15:00'),
            $this->order(2, 1, 100, '2026-01-05 09:00'),
        ];

        $sequence = (new EarliestDueDateStrategy)->sequence($orders, $this->at('2026-01-05 08:00'));

        $this->assertSame([2, 3, 1, 4], array_map(fn (SchedulingOrder $o) => $o->orderId, $sequence));
    }

    #[Test]
    public function it_compares_absolute_instants_not_wall_clock_strings(): void
    {
        $orders = [
            $this->order(1, 1, 100, '2026-01-05 10:30'), // 10:30 UTC
            new SchedulingOrder(2, 'Toronto', 1, 'Type 1', 100, 9, CarbonImmutable::parse('2026-01-05 05:00', 'America/Toronto')), // 10:00 UTC
        ];

        $sequence = (new EarliestDueDateStrategy)->sequence($orders, $this->at('2026-01-05 08:00'));

        $this->assertSame([2, 1], array_map(fn (SchedulingOrder $o) => $o->orderId, $sequence));
    }

    #[Test]
    public function it_handles_empty_input(): void
    {
        $this->assertSame([], (new EarliestDueDateStrategy)->sequence([], $this->at('2026-01-05 08:00')));
    }
}

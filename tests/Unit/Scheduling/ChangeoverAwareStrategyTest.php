<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\DTOs\SchedulingOrder;
use App\Domain\Scheduling\ProductionScheduler;
use App\Domain\Scheduling\Strategies\ChangeoverAwareStrategy;
use App\Domain\Scheduling\Strategies\EarliestDueDateStrategy;
use App\Domain\Scheduling\TimelineSimulator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChangeoverAwareStrategyTest extends TestCase
{
    use BuildsSchedulingOrders;

    private function strategy(int $maxSimulatedOrders = 200_000): ChangeoverAwareStrategy
    {
        return new ChangeoverAwareStrategy(new EarliestDueDateStrategy, new TimelineSimulator(30), $maxSimulatedOrders);
    }

    #[Test]
    public function it_removes_a_changeover_on_the_brief_fixture_without_making_orders_late(): void
    {
        $start = $this->at('2026-01-05 08:00');
        $result = (new ProductionScheduler($this->strategy(), new TimelineSimulator(30)))->generate($this->briefFixture(), $start);

        $this->assertSame(0, $result->lateOrderCount);
        $this->assertSame(30, $result->totalSetupMinutes);
        $this->assertEquals($this->at('2026-01-05 12:30'), $result->endAt);
    }

    #[Test]
    public function it_never_trades_an_on_time_order_for_less_setup(): void
    {
        // Grouping the two Type 1 orders would make the Type 2 order late.
        $orders = [
            $this->order(1, 1, 1430, '2026-01-05 10:00'),
            $this->order(2, 2, 770, '2026-01-05 11:30'),
            $this->order(3, 1, 715, '2026-01-05 13:00'),
        ];
        $start = $this->at('2026-01-05 08:00');

        $sequence = $this->strategy()->sequence($orders, $start);

        $this->assertSame([1, 2, 3], array_map(fn (SchedulingOrder $o) => $o->orderId, $sequence));
    }

    #[Test]
    public function it_is_never_worse_than_edd_and_is_deterministic(): void
    {
        $orders = [];
        foreach (range(1, 12) as $id) {
            $orders[] = $this->order($id, ($id * 7) % 3 + 1, 300 + ($id * 137) % 1500, sprintf('2026-01-05 %02d:%02d', 9 + intdiv($id * 53 % 600, 60), ($id * 53) % 60));
        }
        $start = $this->at('2026-01-05 08:00');
        $simulator = new TimelineSimulator(30);

        $edd = $simulator->simulate((new EarliestDueDateStrategy)->sequence($orders, $start), $start);
        $first = $simulator->simulate($this->strategy()->sequence($orders, $start), $start);
        $second = $simulator->simulate($this->strategy()->sequence(array_reverse($orders), $start), $start);

        $this->assertLessThanOrEqual($edd->qualityKey(), $first->qualityKey());
        $this->assertSame($first->orderIds(), $second->orderIds());
    }

    #[Test]
    public function it_returns_edd_when_the_budget_cannot_cover_one_candidate(): void
    {
        $start = $this->at('2026-01-05 08:00');

        // 3 orders: 3 placements for the EDD baseline + 3 per candidate, so a budget of 5 evaluates nothing.
        foreach ([0, 5] as $budget) {
            $sequence = $this->strategy($budget)->sequence($this->briefFixture(), $start);
            $this->assertSame([1, 2, 3], array_map(fn (SchedulingOrder $o) => $o->orderId, $sequence));
        }
    }

    #[Test]
    public function it_stops_at_the_budget_with_a_result_never_worse_than_edd(): void
    {
        $orders = [];
        foreach (range(1, 30) as $id) {
            $orders[] = $this->order($id, ($id * 7) % 3 + 1, 300 + ($id * 137) % 1500, sprintf('2026-01-05 %02d:%02d', 9 + intdiv($id * 53 % 600, 60), ($id * 53) % 60));
        }
        $start = $this->at('2026-01-05 08:00');
        $simulator = new TimelineSimulator(30);
        $edd = $simulator->simulate((new EarliestDueDateStrategy)->sequence($orders, $start), $start);

        // Room for about 20 candidates out of ~1,200 in the first neighbourhood.
        $small = $simulator->simulate($this->strategy(30 * 21)->sequence($orders, $start), $start);
        $large = $simulator->simulate($this->strategy()->sequence($orders, $start), $start);

        $this->assertLessThanOrEqual($edd->qualityKey(), $small->qualityKey());
        $this->assertLessThanOrEqual($small->qualityKey(), $large->qualityKey());
        $this->assertSame($small->orderIds(), $simulator->simulate($this->strategy(30 * 21)->sequence($orders, $start), $start)->orderIds());
    }

    #[Test]
    public function it_rejects_a_negative_budget(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->strategy(-1);
    }

    #[Test]
    public function it_handles_empty_and_single_order_input(): void
    {
        $start = $this->at('2026-01-05 08:00');

        $this->assertSame([], $this->strategy()->sequence([], $start));
        $this->assertCount(1, $this->strategy()->sequence([$this->order(1, 1, 10, '2026-01-05 12:00')], $start));
    }
}

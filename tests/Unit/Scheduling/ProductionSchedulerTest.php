<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\ProductionScheduler;
use App\Domain\Scheduling\Strategies\EarliestDueDateStrategy;
use App\Domain\Scheduling\TimelineSimulator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The brief's 3-order fixture: start 08:00, 30 min changeover.
 */
final class ProductionSchedulerTest extends TestCase
{
    use BuildsSchedulingOrders;

    #[Test]
    public function edd_on_the_brief_fixture_finishes_at_13_00_with_60_setup_minutes(): void
    {
        $scheduler = new ProductionScheduler(new EarliestDueDateStrategy, new TimelineSimulator(30));

        $result = $scheduler->generate($this->briefFixture(), $this->at('2026-01-05 08:00'));

        $this->assertSame([1, 2, 3], $result->orderIds()); // A, C, B
        $this->assertSame(
            [['08:00', '10:00'], ['10:30', '11:30'], ['12:00', '13:00']],
            array_map(fn ($e) => [$e->productionStartAt->format('H:i'), $e->finishAt->format('H:i')], $result->entries),
        );
        $this->assertSame([0, 30, 30], array_map(fn ($e) => $e->setupMinutes, $result->entries));
        $this->assertSame(60, $result->totalSetupMinutes);
        $this->assertSame(0, $result->lateOrderCount);
        $this->assertEquals($this->at('2026-01-05 13:00'), $result->endAt);
    }

    #[Test]
    public function grouping_a_and_b_finishes_at_12_30_with_30_setup_minutes(): void
    {
        [$a, $c, $b] = $this->briefFixture();

        $result = (new TimelineSimulator(30))->simulate([$a, $b, $c], $this->at('2026-01-05 08:00'));

        $this->assertSame(30, $result->totalSetupMinutes);
        $this->assertSame(0, $result->lateOrderCount);
        $this->assertEquals($this->at('2026-01-05 12:30'), $result->endAt);
    }

    #[Test]
    public function empty_input_produces_an_empty_schedule(): void
    {
        $scheduler = new ProductionScheduler(new EarliestDueDateStrategy, new TimelineSimulator(30));

        $result = $scheduler->generate([], $this->at('2026-01-05 08:00'));

        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, $result->makespanMinutes());
    }
}

<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\DTOs\SchedulingOrder;
use App\Domain\Scheduling\TimelineSimulator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TimelineSimulatorTest extends TestCase
{
    use BuildsSchedulingOrders;

    #[Test]
    public function first_order_has_no_initial_setup(): void
    {
        $result = (new TimelineSimulator(30))->simulate([$this->order(1, 2, 770, '2026-01-05 12:00')], $this->at('2026-01-05 08:00'));

        $entry = $result->entries[0];
        $this->assertSame(0, $entry->setupMinutes);
        $this->assertEquals($this->at('2026-01-05 08:00'), $entry->productionStartAt);
        $this->assertEquals($this->at('2026-01-05 09:00'), $entry->finishAt);
    }

    #[Test]
    public function consecutive_orders_of_the_same_type_need_no_setup(): void
    {
        $result = (new TimelineSimulator(30))->simulate([
            $this->order(1, 1, 715, '2026-01-05 12:00'),
            $this->order(2, 1, 715, '2026-01-05 12:00'),
        ], $this->at('2026-01-05 08:00'));

        $this->assertSame(0, $result->entries[1]->setupMinutes);
        $this->assertEquals($this->at('2026-01-05 09:00'), $result->entries[1]->productionStartAt);
        $this->assertSame(0, $result->changeoverCount);
    }

    #[Test]
    public function a_type_change_adds_the_configured_setup(): void
    {
        $result = (new TimelineSimulator(45))->simulate([
            $this->order(1, 1, 715, '2026-01-05 12:00'),
            $this->order(2, 3, 1000, '2026-01-05 12:00'),
        ], $this->at('2026-01-05 08:00'));

        $second = $result->entries[1];
        $this->assertSame(45, $second->setupMinutes);
        $this->assertEquals($this->at('2026-01-05 09:00'), $second->setupStartAt);
        $this->assertEquals($this->at('2026-01-05 09:45'), $second->productionStartAt);
        $this->assertEquals($this->at('2026-01-05 10:45'), $second->finishAt);
        $this->assertSame(1, $result->changeoverCount);
    }

    #[Test]
    public function finishing_exactly_at_the_deadline_is_on_time(): void
    {
        $result = (new TimelineSimulator(30))->simulate([$this->order(1, 2, 770, '2026-01-05 09:00')], $this->at('2026-01-05 08:00'));

        $this->assertFalse($result->entries[0]->isLate());
        $this->assertSame(0, $result->entries[0]->latenessMinutes);
        $this->assertSame(0, $result->lateOrderCount);
    }

    #[Test]
    public function finishing_one_minute_after_the_deadline_is_late(): void
    {
        $result = (new TimelineSimulator(30))->simulate([$this->order(1, 2, 770, '2026-01-05 09:00')], $this->at('2026-01-05 08:01'));

        $this->assertTrue($result->entries[0]->isLate());
        $this->assertSame(1, $result->entries[0]->tardinessMinutes);
        $this->assertSame(1, $result->lateOrderCount);
        $this->assertSame(1, $result->totalTardinessMinutes);
    }

    #[Test]
    public function early_orders_have_negative_lateness_and_zero_tardiness(): void
    {
        $entry = (new TimelineSimulator(30))
            ->simulate([$this->order(1, 2, 770, '2026-01-05 10:00')], $this->at('2026-01-05 08:00'))
            ->entries[0];

        $this->assertSame(-60, $entry->latenessMinutes);
        $this->assertSame(0, $entry->tardinessMinutes);
    }

    #[Test]
    public function empty_input_gives_an_empty_schedule_with_zero_metrics(): void
    {
        $start = $this->at('2026-01-05 08:00');
        $result = (new TimelineSimulator(30))->simulate([], $start);

        $this->assertTrue($result->isEmpty());
        $this->assertSame([0, 0, 0, 0], $result->qualityKey());
        $this->assertSame(0, $result->totalProductionMinutes);
        $this->assertSame(0, $result->changeoverCount);
        $this->assertEquals($start, $result->endAt);
    }

    #[Test]
    public function it_computes_summary_metrics(): void
    {
        $result = (new TimelineSimulator(30))->simulate($this->briefFixture(), $this->at('2026-01-05 08:00'));

        $this->assertSame(240, $result->totalProductionMinutes);
        $this->assertSame(60, $result->totalSetupMinutes);
        $this->assertSame(300, $result->makespanMinutes());
        $this->assertSame(2, $result->changeoverCount);
        $this->assertEquals($this->at('2026-01-05 13:00'), $result->endAt);
    }

    #[Test]
    public function it_rejects_negative_changeover_minutes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TimelineSimulator(-1);
    }

    #[Test]
    public function zero_changeover_is_allowed_and_type_switches_are_still_counted(): void
    {
        // A (Type 1) → C (Type 2) → B (Type 1): two switches, each costing 0 minutes.
        $result = (new TimelineSimulator(0))->simulate($this->briefFixture(), $this->at('2026-01-05 08:00'));

        $this->assertSame(0, $result->totalSetupMinutes);
        $this->assertSame(2, $result->changeoverCount);
        $this->assertEquals($this->at('2026-01-05 12:00'), $result->endAt);
    }

    #[Test]
    public function it_rejects_zero_or_negative_production_durations(): void
    {
        foreach ([0, -10] as $minutes) {
            try {
                new SchedulingOrder(1, 'X', 1, 'Type 1', 1, $minutes, $this->at('2026-01-05 12:00'));
                $this->fail("Duration {$minutes} should have been rejected.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function it_does_not_mutate_input_timestamps(): void
    {
        $start = $this->at('2026-01-05 08:00');
        $orders = $this->briefFixture();
        $dueBefore = array_map(fn (SchedulingOrder $o) => $o->dueAt->toIso8601String(), $orders);

        (new TimelineSimulator(30))->simulate($orders, $start);

        $this->assertInstanceOf(CarbonImmutable::class, $start);
        $this->assertSame('2026-01-05T08:00:00+00:00', $start->toIso8601String());
        $this->assertSame($dueBefore, array_map(fn (SchedulingOrder $o) => $o->dueAt->toIso8601String(), $orders));
    }

    #[Test]
    public function timestamps_keep_absolute_time_whatever_the_input_timezone(): void
    {
        $startToronto = CarbonImmutable::parse('2026-01-05 03:00', 'America/Toronto'); // 08:00 UTC
        $result = (new TimelineSimulator(30))->simulate([$this->order(1, 2, 770, '2026-01-05 09:00')], $startToronto);

        $this->assertSame(0, $result->entries[0]->latenessMinutes);
    }
}

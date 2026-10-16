<?php

namespace App\Http\Controllers;

use App\Data\ScheduleQueryData;
use App\Domain\Scheduling\Contracts\SchedulingStrategy;
use App\Domain\Scheduling\OrderSchedulingMapper;
use App\Domain\Scheduling\ProductionScheduler;
use App\Domain\Scheduling\Strategies\ChangeoverAwareStrategy;
use App\Domain\Scheduling\Strategies\EarliestDueDateStrategy;
use App\Domain\Scheduling\TimelineSimulator;
use App\Models\Order;
use App\Support\DisplayTime;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __invoke(
        ScheduleQueryData $query,
        OrderSchedulingMapper $mapper,
        TimelineSimulator $simulator,
        EarliestDueDateStrategy $edd,
        ChangeoverAwareStrategy $changeoverAware,
    ): View {
        /** @var array<string, SchedulingStrategy> $strategies */
        $strategies = [$edd->key() => $edd, $changeoverAware->key() => $changeoverAware];

        $timezone = DisplayTime::timezone();
        $startAt = $query->startAtUtc();
        $strategy = $strategies[$query->strategy];

        $mapped = $mapper->map(
            Order::query()->with('items.product.productType')->orderBy('id')->get()
        );
        $orders = $mapped->orders;

        // EDD is cheap and is the baseline every other strategy is compared with; the selected strategy is
        // only computed when it is not EDD (the heuristic is the expensive one).
        $comparison = [];
        foreach (array_unique([$edd->key(), $strategy->key()]) as $key) {
            $comparison[$key] = [
                'label' => $strategies[$key]->label(),
                'result' => (new ProductionScheduler($strategies[$key], $simulator))->generate($orders, $startAt),
            ];
        }

        return view('schedule.index', [
            'schedule' => $comparison[$strategy->key()]['result'],
            'comparison' => $comparison,
            'strategy' => $strategy,
            'strategies' => $strategies,
            'startAt' => $startAt,
            'timezone' => $timezone,
            'changeoverMinutes' => $simulator->changeoverMinutes(),
            'skippedOrders' => $mapped->skipped,
        ]);
    }
}

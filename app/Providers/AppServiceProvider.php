<?php

namespace App\Providers;

use App\Domain\Scheduling\Strategies\ChangeoverAwareStrategy;
use App\Domain\Scheduling\Strategies\EarliestDueDateStrategy;
use App\Domain\Scheduling\TimelineSimulator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TimelineSimulator::class, fn (): TimelineSimulator => new TimelineSimulator(
            (int) config('scheduling.changeover_minutes'),
        ));

        $this->app->bind(ChangeoverAwareStrategy::class, fn ($app): ChangeoverAwareStrategy => new ChangeoverAwareStrategy(
            $app->make(EarliestDueDateStrategy::class),
            $app->make(TimelineSimulator::class),
            (int) config('scheduling.heuristic.max_simulated_orders'),
        ));
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
    }
}

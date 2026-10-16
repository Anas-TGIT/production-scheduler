<?php

namespace App\Data;

use App\Domain\Scheduling\Strategies\ChangeoverAwareStrategy;
use App\Domain\Scheduling\Strategies\EarliestDueDateStrategy;
use App\Support\DisplayTime;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class ScheduleQueryData extends Data
{
    public function __construct(
        public readonly ?string $start = null,
        public readonly string $strategy = EarliestDueDateStrategy::KEY,
    ) {}

    public static function rules(): array
    {
        return [
            'start' => ['nullable', 'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s'],
            'strategy' => ['sometimes', 'in:'.EarliestDueDateStrategy::KEY.','.ChangeoverAwareStrategy::KEY],
        ];
    }

    /** Explicit start (wall-clock in the display timezone) or the next whole minute from now, in UTC. */
    public function startAtUtc(): CarbonImmutable
    {
        return $this->start !== null
            ? DisplayTime::toUtc($this->start)
            : CarbonImmutable::now('UTC')->ceilMinute();
    }
}

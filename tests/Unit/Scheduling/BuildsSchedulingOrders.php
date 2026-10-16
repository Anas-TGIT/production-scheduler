<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\DTOs\SchedulingOrder;
use App\Domain\Scheduling\ProductionDurationCalculator;
use Carbon\CarbonImmutable;

trait BuildsSchedulingOrders
{
    /** Units per hour for the brief's three types. */
    private const RATES = [1 => 715, 2 => 770, 3 => 1000];

    private function order(int $id, int $typeId, int $quantity, string $dueAt): SchedulingOrder
    {
        return new SchedulingOrder(
            orderId: $id,
            customerName: "Customer {$id}",
            productTypeId: $typeId,
            productTypeName: "Type {$typeId}",
            totalQuantity: $quantity,
            productionMinutes: (new ProductionDurationCalculator)->minutesFor([$quantity], self::RATES[$typeId]),
            dueAt: CarbonImmutable::parse($dueAt, 'UTC'),
        );
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, 'UTC');
    }

    /** @return list<SchedulingOrder> A 1,430 due 12:00; C 770 due 15:00; B 715 due 17:00 */
    private function briefFixture(): array
    {
        return [
            $this->order(1, 1, 1430, '2026-01-05 12:00'),
            $this->order(2, 2, 770, '2026-01-05 15:00'),
            $this->order(3, 1, 715, '2026-01-05 17:00'),
        ];
    }
}

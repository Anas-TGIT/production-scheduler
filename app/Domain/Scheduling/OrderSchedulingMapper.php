<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\DTOs\MappedOrders;
use App\Domain\Scheduling\DTOs\SchedulingOrder;
use App\Models\Order;
use App\Models\OrderItem;
use DomainException;
use InvalidArgumentException;

/**
 * Converts Eloquent orders (with items.product.productType eager loaded) into engine DTOs.
 */
final class OrderSchedulingMapper
{
    public function __construct(private readonly ProductionDurationCalculator $calculator) {}

    /**
     * Orders that cannot be scheduled (no items, mixed types after a catalogue change, invalid rate) are left out
     * and reported, so one bad row never prevents scheduling all the others.
     *
     * @param  iterable<Order>  $orders
     */
    public function map(iterable $orders): MappedOrders
    {
        $mapped = [];
        $skipped = [];

        foreach ($orders as $order) {
            try {
                $mapped[] = $this->mapOne($order);
            } catch (DomainException|InvalidArgumentException $exception) {
                $skipped[$order->id] = $exception->getMessage();
            }
        }

        return new MappedOrders($mapped, $skipped);
    }

    public function mapOne(Order $order): SchedulingOrder
    {
        $items = $order->items;

        if ($items->isEmpty()) {
            throw new DomainException("Order #{$order->id} has no items and cannot be scheduled.");
        }

        $types = $items->map(fn (OrderItem $item) => $item->product->productType)->unique('id');

        if ($types->count() !== 1) {
            throw new DomainException("Order #{$order->id} mixes product types and cannot be scheduled.");
        }

        $type = $types->first();
        $quantities = $items->pluck('quantity')->map(fn ($quantity): int => (int) $quantity)->values()->all();

        return new SchedulingOrder(
            orderId: $order->id,
            customerName: $order->customer_name,
            productTypeId: $type->id,
            productTypeName: $type->name,
            totalQuantity: array_sum($quantities),
            productionMinutes: $this->calculator->minutesFor($quantities, $type->units_per_hour),
            dueAt: $order->need_by_at->utc(),
        );
    }
}

<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * One product line of an order. Validated by OrderFormData (rules use the items.* paths).
 */
class OrderItemData extends Data
{
    public const MAX_QUANTITY = 1_000_000;

    public function __construct(
        public readonly int $product_id,
        public readonly int $quantity,
    ) {}
}

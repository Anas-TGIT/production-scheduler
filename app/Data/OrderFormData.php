<?php

namespace App\Data;

use App\Data\Rules\FutureDeadlineRule;
use App\Data\Rules\SameProductTypeRule;
use App\Models\Order;
use App\Support\DisplayTime;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class OrderFormData extends Data
{
    public const MAX_ITEMS = 20;

    public function __construct(
        public readonly string $customer_name,
        // Wall-clock time from <input type="datetime-local">, interpreted in the display timezone.
        public readonly string $need_by_at,
        /** @var DataCollection<int, OrderItemData> */
        #[DataCollectionOf(OrderItemData::class)]
        public readonly DataCollection $items,
    ) {}

    public static function rules(): array
    {
        $editedOrder = request()->route('order');

        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'need_by_at' => [
                'bail', 'required', 'date_format:Y-m-d\TH:i,Y-m-d\TH:i:s',
                new FutureDeadlineRule($editedOrder instanceof Order ? $editedOrder : null),
            ],
            'items' => ['bail', 'required', 'array', 'min:1', 'max:'.self::MAX_ITEMS, new SameProductTypeRule],
            'items.*' => ['required', 'array'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.OrderItemData::MAX_QUANTITY],
        ];
    }

    public static function messages(...$args): array
    {
        return [
            'items.required' => 'Add at least one product line.',
            'items.min' => 'Add at least one product line.',
            'items.*.product_id.required' => 'Choose a product on every line.',
            'items.*.product_id.distinct' => 'Each product can appear only once; combine the quantities instead.',
            'items.*.product_id.exists' => 'The selected product does not exist.',
            'items.*.quantity.required' => 'Enter a quantity on every line.',
            'items.*.quantity.integer' => 'Quantities must be whole numbers.',
            'items.*.quantity.min' => 'Quantities must be at least 1.',
            'need_by_at.date_format' => 'Enter the need-by date and time.',
        ];
    }

    public static function attributes(...$args): array
    {
        return [
            'customer_name' => 'customer name',
            'need_by_at' => 'need-by date',
            'items.*.product_id' => 'product',
            'items.*.quantity' => 'quantity',
        ];
    }

    public function needByUtc(): CarbonImmutable
    {
        return DisplayTime::toUtc($this->need_by_at);
    }

    /** @return list<array{product_id: int, quantity: int}> */
    public function itemAttributes(): array
    {
        return array_values($this->items->toArray());
    }
}

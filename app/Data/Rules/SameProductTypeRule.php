<?php

namespace App\Data\Rules;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SameProductTypeRule implements ValidationRule
{
    private const string MESSAGE = 'All products in one order must share the same product type (found: :types). Split it into separate orders.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $productIds = collect(is_array($value) ? $value : [])
            ->filter(fn ($item) => is_array($item) && is_numeric($item['product_id'] ?? null))
            ->map(fn (array $item) => (int) $item['product_id']);

        $typeNames = Product::query()
            ->with('productType:id,name')
            ->whereKey($productIds)
            ->get()
            ->pluck('productType.name', 'product_type_id');

        if ($typeNames->count() > 1) {
            $fail(str_replace(':types', $typeNames->sort()->implode(', '), self::MESSAGE));
        }
    }
}

<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Product '.fake()->unique()->bothify('??##'),
            'product_type_id' => ProductType::factory(),
        ];
    }
}

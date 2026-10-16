<?php

namespace Database\Factories;

use App\Models\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductType> */
class ProductTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Type '.fake()->unique()->numberBetween(100, 999),
            'units_per_hour' => fake()->numberBetween(100, 2000),
        ];
    }
}

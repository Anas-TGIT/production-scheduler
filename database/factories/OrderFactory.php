<?php

namespace Database\Factories;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_name' => fake()->company(),
            'need_by_at' => CarbonImmutable::now('UTC')->addDays(2)->startOfHour(),
        ];
    }
}

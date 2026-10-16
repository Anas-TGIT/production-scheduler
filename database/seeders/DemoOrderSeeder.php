<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reproducible demo backlog, only loaded into an empty orders table: deadlines are fixed clock times relative to "tomorrow"
 * in the display timezone, so the demo looks the same whenever it is seeded.
 * Schedule it from tomorrow 08:00 to reproduce the README example.
 */
class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        // Not additive: running `db:seed` again must not duplicate the backlog.
        if (Order::query()->exists()) {
            $this->command?->warn('Orders already exist: demo orders not added.');

            return;
        }

        $timezone = (string) config('scheduling.display_timezone');
        $tomorrow = CarbonImmutable::now($timezone)->addDay()->startOfDay();
        $products = Product::query()->pluck('id', 'name');

        $orders = [
            ['Acme Corp', '12:00', 0, ['A' => 1430]],
            ['Globex', '15:00', 0, ['C' => 770]],
            ['Initech', '17:00', 0, ['B' => 715, 'F' => 715]],
            ['Umbrella', '18:00', 0, ['D' => 2000, 'E' => 1000]],
            ['Stark Industries', '09:00', 1, ['A' => 500]],
            ['Wayne Enterprises', '12:00', 1, ['E' => 1500]],
        ];

        DB::transaction(function () use ($orders, $tomorrow, $products): void {
            foreach ($orders as [$customer, $time, $dayOffset, $items]) {
                [$hour, $minute] = array_map('intval', explode(':', $time));

                $order = Order::create([
                    'customer_name' => $customer,
                    'need_by_at' => $tomorrow->addDays($dayOffset)->setTime($hour, $minute)->utc(),
                ]);

                foreach ($items as $productName => $quantity) {
                    $order->items()->create([
                        'product_id' => $products[$productName],
                        'quantity' => $quantity,
                    ]);
                }
            }
        });
    }
}

<?php

namespace Tests\Feature;

use App\Domain\Scheduling\OrderSchedulingMapper;
use App\Models\Order;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductCatalogSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scheduling.display_timezone' => 'America/Toronto', 'scheduling.changeover_minutes' => 30]);
        $this->seed(ProductCatalogSeeder::class);
    }

    /** @param array<string, int> $items */
    private function createOrder(string $customer, string $dueLocal, array $items): Order
    {
        $order = Order::create([
            'customer_name' => $customer,
            'need_by_at' => CarbonImmutable::parse($dueLocal, 'America/Toronto')->utc(),
        ]);
        foreach ($items as $name => $quantity) {
            $order->items()->create(['product_id' => Product::where('name', $name)->value('id'), 'quantity' => $quantity]);
        }

        return $order;
    }

    private function seedBriefFixture(): array
    {
        return [
            $this->createOrder('Alpha', '2026-01-05 12:00', ['A' => 1430]),
            $this->createOrder('Charlie', '2026-01-05 15:00', ['C' => 770]),
            $this->createOrder('Bravo', '2026-01-05 17:00', ['B' => 715]),
        ];
    }

    #[Test]
    public function it_shows_an_empty_state_without_orders(): void
    {
        $this->get('/schedule')->assertOk()->assertSee('No orders yet');
    }

    #[Test]
    public function the_home_page_redirects_to_the_schedule(): void
    {
        $this->get('/')->assertRedirect('/schedule');
    }

    #[Test]
    public function it_displays_the_edd_sequence_with_times_in_the_display_timezone(): void
    {
        [$a, $c, $b] = $this->seedBriefFixture();

        $this->get('/schedule?start=2026-01-05T08:00')
            ->assertOk()
            ->assertSeeInOrder([
                "data-order-id=\"{$a->id}\"", 'Mon 05 Jan, 08:00', '2h 00m', 'Mon 05 Jan, 10:00', 'On time',
                "data-order-id=\"{$c->id}\"", '30m', 'Mon 05 Jan, 10:30', 'Mon 05 Jan, 11:30', 'On time',
                "data-order-id=\"{$b->id}\"", '30m', 'Mon 05 Jan, 12:00', 'Mon 05 Jan, 13:00', 'On time',
            ], false);
    }

    #[Test]
    public function it_flags_late_orders(): void
    {
        $late = $this->createOrder('Tight deadline', '2026-01-05 08:59', ['C' => 770]);

        $this->get('/schedule?start=2026-01-05T08:00')
            ->assertOk()
            ->assertSeeInOrder(["data-order-id=\"{$late->id}\"", 'Late by 1m'], false);
    }

    #[Test]
    public function the_heuristic_view_shows_the_improved_metrics_and_the_comparison(): void
    {
        $this->seedBriefFixture();

        $response = $this->get('/schedule?start=2026-01-05T08:00&strategy=changeover')->assertOk();

        $schedule = $response->viewData('schedule');
        $comparison = $response->viewData('comparison');
        $this->assertSame(30, $schedule->totalSetupMinutes);
        $this->assertSame('2026-01-05 17:30', $schedule->endAt->format('Y-m-d H:i')); // 12:30 Toronto (UTC−5)
        $this->assertSame(60, $comparison['edd']['result']->totalSetupMinutes);
        $response->assertSee('Strategy comparison');
    }

    #[Test]
    public function the_default_edd_view_does_not_run_the_heuristic(): void
    {
        $this->seedBriefFixture();

        $response = $this->get('/schedule?start=2026-01-05T08:00')->assertOk();

        $this->assertSame(['edd'], array_keys($response->viewData('comparison')));
        $response->assertSee('Try the changeover-aware heuristic')->assertDontSee('Strategy comparison');
    }

    #[Test]
    public function an_order_that_cannot_be_scheduled_is_left_out_with_a_warning(): void
    {
        [$a] = $this->seedBriefFixture();
        $broken = $this->createOrder('Broken', '2026-01-05 12:00', ['A' => 10, 'C' => 10]);

        $response = $this->get('/schedule?start=2026-01-05T08:00')->assertOk();

        $this->assertNotContains($broken->id, $response->viewData('schedule')->orderIds());
        $this->assertCount(3, $response->viewData('schedule')->entries);
        $response->assertSee('1 order(s) could not be scheduled')
            ->assertSee("Order #{$broken->id} mixes product types")
            ->assertSee(route('orders.edit', $broken), false)
            ->assertSee("data-order-id=\"{$a->id}\"", false);
    }

    #[Test]
    public function it_rejects_unknown_strategies_and_malformed_start_times(): void
    {
        $this->get('/schedule?strategy=random')->assertSessionHasErrors('strategy');
        $this->get('/schedule?start=tomorrow')->assertSessionHasErrors('start');
    }

    #[Test]
    public function the_mapper_refuses_orders_that_mix_types_in_storage(): void
    {
        $order = $this->createOrder('Corrupted', '2026-01-05 12:00', ['A' => 10, 'C' => 10]);

        $this->expectException(DomainException::class);

        app(OrderSchedulingMapper::class)->mapOne($order->load('items.product.productType'));
    }

    #[Test]
    public function the_mapper_sums_quantities_before_rounding(): void
    {
        $order = $this->createOrder('Split', '2026-01-05 12:00', ['A' => 75, 'B' => 75]);

        $dto = app(OrderSchedulingMapper::class)->mapOne($order->load('items.product.productType'));

        $this->assertSame(150, $dto->totalQuantity);
        $this->assertSame(13, $dto->productionMinutes);
        $this->assertSame('Type 1', $dto->productTypeName);
    }
}

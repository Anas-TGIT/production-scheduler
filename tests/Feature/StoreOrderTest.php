<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class StoreOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Frozen clock so fixed dates in fixtures stay in the future. In Toronto it is 2025-12-31 19:00.
        $this->travelTo(CarbonImmutable::parse('2026-01-01 00:00', 'UTC'));
        config(['scheduling.display_timezone' => 'America/Toronto']);
        $this->seed(ProductCatalogSeeder::class);
    }

    private function productId(string $name): int
    {
        return Product::where('name', $name)->value('id');
    }

    /** @param array<string, int> $items product name => quantity */
    private function payload(array $items = ['A' => 1000, 'B' => 430], array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Acme Corp',
            'need_by_at' => '2026-11-20T14:30',
            'items' => collect($items)->map(fn ($qty, $name) => ['product_id' => $this->productId($name), 'quantity' => $qty])->values()->all(),
        ], $overrides);
    }

    private function assertNothingPersisted(): void
    {
        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
    }

    #[Test]
    public function the_form_renders_products_grouped_by_type(): void
    {
        $this->get('/orders/create')->assertOk()->assertSee('Type 1 — 715 units/h')->assertSee('America/Toronto');
    }

    #[Test]
    public function it_creates_an_order_with_several_items_of_the_same_type(): void
    {
        $this->post('/orders', $this->payload(['A' => 1000, 'B' => 430, 'F' => 5]))
            ->assertRedirect('/schedule')
            ->assertSessionHas('status');

        $order = Order::with('items')->sole();
        $this->assertSame('Acme Corp', $order->customer_name);
        $this->assertSame([1000, 430, 5], $order->items->pluck('quantity')->all());
    }

    #[Test]
    public function it_rejects_items_of_different_product_types(): void
    {
        $this->from('/orders/create')
            ->post('/orders', $this->payload(['A' => 100, 'C' => 100]))
            ->assertRedirect('/orders/create')
            ->assertSessionHasErrors(['items' => 'All products in one order must share the same product type (found: Type 1, Type 2). Split it into separate orders.']);

        $this->assertNothingPersisted();
    }

    #[Test]
    public function it_ignores_client_supplied_type_ids(): void
    {
        $payload = $this->payload(['A' => 100, 'D' => 100]);
        $payload['items'][0]['product_type_id'] = 3;
        $payload['product_type_id'] = 3;

        $this->post('/orders', $payload)->assertSessionHasErrors('items');
        $this->assertNothingPersisted();
    }

    #[Test]
    #[DataProvider('invalidPayloads')]
    public function it_rejects_invalid_payloads(callable $mutate, string $errorKey): void
    {
        $payload = $mutate($this->payload());

        $this->post('/orders', $payload)->assertSessionHasErrors($errorKey);
        $this->assertNothingPersisted();
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing customer' => [fn ($p) => ['customer_name' => '   '] + $p, 'customer_name'],
            'no items' => [fn ($p) => ['items' => []] + $p, 'items'],
            'items missing' => [function ($p) {
                unset($p['items']);

                return $p;
            }, 'items'],
            'nonexistent product' => [function ($p) {
                $p['items'][0]['product_id'] = 9999;

                return $p;
            }, 'items.0.product_id'],
            'zero quantity' => [function ($p) {
                $p['items'][0]['quantity'] = 0;

                return $p;
            }, 'items.0.quantity'],
            'negative quantity' => [function ($p) {
                $p['items'][0]['quantity'] = -5;

                return $p;
            }, 'items.0.quantity'],
            'decimal quantity' => [function ($p) {
                $p['items'][0]['quantity'] = '1.5';

                return $p;
            }, 'items.0.quantity'],
            'text quantity' => [function ($p) {
                $p['items'][0]['quantity'] = 'many';

                return $p;
            }, 'items.0.quantity'],
            'duplicate product' => [function ($p) {
                $p['items'][1]['product_id'] = $p['items'][0]['product_id'];

                return $p;
            }, 'items.1.product_id'],
            'malformed deadline' => [fn ($p) => ['need_by_at' => 'next friday'] + $p, 'need_by_at'],
            'impossible date' => [fn ($p) => ['need_by_at' => '2026-02-30T10:00'] + $p, 'need_by_at'],
            'missing deadline' => [fn ($p) => ['need_by_at' => ''] + $p, 'need_by_at'],
        ];
    }

    #[Test]
    public function a_failure_while_writing_items_rolls_back_the_whole_order(): void
    {
        OrderItem::creating(function (OrderItem $item): void {
            if ($item->quantity === 430) {
                throw new RuntimeException('Simulated failure on the second line');
            }
        });

        $this->withoutExceptionHandling();

        try {
            $this->post('/orders', $this->payload(['A' => 1000, 'B' => 430]));
            $this->fail('Expected the simulated failure.');
        } catch (RuntimeException) {
            $this->assertNothingPersisted();
        }
    }

    #[Test]
    public function deadlines_are_interpreted_in_the_display_timezone_and_stored_in_utc(): void
    {
        // Winter: Toronto is UTC−5 (EST)
        $this->post('/orders', $this->payload(overrides: ['need_by_at' => '2026-01-15T12:00']));
        // Summer: Toronto is UTC−4 (EDT)
        $this->post('/orders', $this->payload(overrides: ['need_by_at' => '2026-07-15T12:00']));

        $this->assertSame(
            ['2026-01-15 17:00:00', '2026-07-15 16:00:00'],
            Order::orderBy('id')->get()->map(fn (Order $o) => $o->need_by_at->utc()->format('Y-m-d H:i:s'))->all(),
        );
        $this->assertDatabaseHas('orders', ['need_by_at' => '2026-01-15 17:00:00']);
    }

    #[Test]
    public function past_deadlines_are_rejected(): void
    {
        $this->post('/orders', $this->payload(overrides: ['need_by_at' => '2020-01-01T08:00']))
            ->assertSessionHasErrors(['need_by_at' => 'The need-by date must be in the future (America/Toronto time).']);

        $this->assertNothingPersisted();
    }

    #[Test]
    public function a_deadline_equal_to_now_is_rejected(): void
    {
        $this->post('/orders', $this->payload(overrides: ['need_by_at' => '2025-12-31T19:00']))->assertSessionHasErrors('need_by_at');

        $this->assertNothingPersisted();
    }

    #[Test]
    public function the_future_check_uses_the_display_timezone(): void
    {
        // 18:59 Toronto = 23:59 UTC: one minute before now.
        $this->post('/orders', $this->payload(overrides: ['need_by_at' => '2025-12-31T18:59']))->assertSessionHasErrors('need_by_at');

        // 20:00 Toronto = 01:00 UTC next day: in the future. Laravel's `after:now` would wrongly reject it
        // because it would read "2025-12-31 20:00" as UTC.
        $this->post('/orders', $this->payload(overrides: ['need_by_at' => '2025-12-31T20:00']))->assertSessionHasNoErrors();

        $this->assertSame('2026-01-01 01:00:00', Order::sole()->need_by_at->utc()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function the_form_suggests_now_as_the_earliest_deadline(): void
    {
        $this->get('/orders/create')->assertSee('min="2025-12-31T19:00"', false);
    }
}

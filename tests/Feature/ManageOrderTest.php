<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ManageOrderTest extends TestCase
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
    private function createOrder(array $items = ['A' => 1000, 'B' => 430], string $dueLocal = '2026-11-20 14:30'): Order
    {
        $order = Order::create([
            'customer_name' => 'Acme Corp',
            'need_by_at' => CarbonImmutable::parse($dueLocal, 'America/Toronto')->utc(),
        ]);
        foreach ($items as $name => $quantity) {
            $order->items()->create(['product_id' => $this->productId($name), 'quantity' => $quantity]);
        }

        return $order;
    }

    /** @param array<string, int> $items */
    private function payload(array $items, array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'Acme Corp (revised)',
            'need_by_at' => '2026-11-21T09:15',
            'items' => collect($items)->map(fn ($qty, $name) => ['product_id' => $this->productId($name), 'quantity' => $qty])->values()->all(),
        ], $overrides);
    }

    #[Test]
    public function the_edit_form_is_prefilled_with_the_order_in_the_display_timezone(): void
    {
        $order = $this->createOrder();

        $this->get("/orders/{$order->id}/edit")
            ->assertOk()
            ->assertSee("Edit order #{$order->id}")
            ->assertSee('value="Acme Corp"', false)
            ->assertSee('value="2026-11-20T14:30"', false)
            ->assertSee('value="1000"', false)
            ->assertSee('value="430"', false)
            ->assertSee('Save changes')
            ->assertSee('Delete this order');
    }

    #[Test]
    public function it_updates_the_order_and_replaces_its_lines(): void
    {
        $order = $this->createOrder();

        $this->put("/orders/{$order->id}", $this->payload(['F' => 715]))
            ->assertRedirect('/schedule')
            ->assertSessionHas('status', "Order #{$order->id} for Acme Corp (revised) was updated.");

        $order->refresh()->load('items.product');
        $this->assertSame('Acme Corp (revised)', $order->customer_name);
        $this->assertSame('2026-11-21 14:15:00', $order->need_by_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(['F' => 715], $order->items->mapWithKeys(fn (OrderItem $i) => [$i->product->name => $i->quantity])->all());
        $this->assertSame(1, OrderItem::count());
    }

    #[Test]
    public function it_can_switch_an_order_to_another_product_type(): void
    {
        $order = $this->createOrder(['A' => 100]);

        $this->put("/orders/{$order->id}", $this->payload(['D' => 500, 'E' => 500]))->assertSessionHasNoErrors();

        $this->assertSame(['Type 3'], $order->fresh()->items->map(fn (OrderItem $i) => $i->product->productType->name)->unique()->values()->all());
    }

    #[Test]
    public function an_invalid_update_leaves_the_order_untouched(): void
    {
        $order = $this->createOrder();
        $before = $order->load('items')->toArray();

        $this->from("/orders/{$order->id}/edit")
            ->put("/orders/{$order->id}", $this->payload(['A' => 100, 'C' => 100]))
            ->assertRedirect("/orders/{$order->id}/edit")
            ->assertSessionHasErrors('items');

        $this->put("/orders/{$order->id}", $this->payload(['A' => 0]))->assertSessionHasErrors('items.0.quantity');

        $this->assertEquals($before, $order->fresh()->load('items')->toArray());
    }

    #[Test]
    public function an_order_whose_deadline_has_passed_can_keep_it_when_edited(): void
    {
        $order = $this->createOrder(['A' => 100], dueLocal: '2025-12-01 10:00');

        $this->get("/orders/{$order->id}/edit")->assertOk()->assertDontSee('min="2025-', false);

        $this->put("/orders/{$order->id}", $this->payload(['A' => 100], ['customer_name' => 'Typo fixed', 'need_by_at' => '2025-12-01T10:00']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Typo fixed', $order->fresh()->customer_name);
    }

    #[Test]
    public function an_edited_deadline_cannot_be_moved_into_the_past(): void
    {
        $order = $this->createOrder(['A' => 100], dueLocal: '2025-12-01 10:00');

        $this->put("/orders/{$order->id}", $this->payload(['A' => 100], ['need_by_at' => '2025-12-01T09:00']))
            ->assertSessionHasErrors('need_by_at');

        $future = $this->createOrder(['A' => 100]);
        $this->put("/orders/{$future->id}", $this->payload(['A' => 100], ['need_by_at' => '2025-06-01T09:00']))
            ->assertSessionHasErrors('need_by_at');

        $this->assertSame('2025-12-01 15:00:00', $order->fresh()->need_by_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-20 19:30:00', $future->fresh()->need_by_at->utc()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_deletes_an_order_and_its_lines(): void
    {
        $kept = $this->createOrder(['C' => 770]);
        $deleted = $this->createOrder();

        $this->delete("/orders/{$deleted->id}")
            ->assertRedirect('/schedule')
            ->assertSessionHas('status', "Order #{$deleted->id} for Acme Corp was deleted.");

        $this->assertModelMissing($deleted);
        $this->assertSame(0, OrderItem::where('order_id', $deleted->id)->count());
        $this->assertModelExists($kept);
        $this->get('/schedule')->assertDontSee("data-order-id=\"{$deleted->id}\"", false);
    }

    #[Test]
    public function unknown_orders_return_404(): void
    {
        $this->get('/orders/999/edit')->assertNotFound();
        $this->put('/orders/999', $this->payload(['A' => 1]))->assertNotFound();
        $this->delete('/orders/999')->assertNotFound();
    }

    #[Test]
    public function the_schedule_links_to_edit_and_delete_each_order(): void
    {
        $order = $this->createOrder();

        $this->get('/schedule')
            ->assertOk()
            ->assertSee(route('orders.edit', $order), false)
            ->assertSee('action="'.route('orders.destroy', $order).'"', false);
    }
}

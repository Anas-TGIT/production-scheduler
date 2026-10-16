<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductType;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProductCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_the_six_products_with_their_types_and_speeds(): void
    {
        $this->seed(ProductCatalogSeeder::class);
        $this->seed(ProductCatalogSeeder::class); // idempotent

        $this->assertSame(3, ProductType::count());
        $this->assertSame(
            ['Type 1' => 715, 'Type 2' => 770, 'Type 3' => 1000],
            ProductType::orderBy('name')->pluck('units_per_hour', 'name')->all(),
        );
        $this->assertSame(
            ['A' => 'Type 1', 'B' => 'Type 1', 'C' => 'Type 2', 'D' => 'Type 3', 'E' => 'Type 3', 'F' => 'Type 1'],
            Product::with('productType')->orderBy('name')->get()->mapWithKeys(fn (Product $p) => [$p->name => $p->productType->name])->all(),
        );
    }

    #[Test]
    public function the_demo_dataset_has_several_deadlines_and_type_changes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/schedule')->assertOk()->assertSee('Acme Corp')->assertSee('Wayne Enterprises');
        $this->assertGreaterThan(1, Order::distinct('need_by_at')->count('need_by_at'));
    }

    #[Test]
    public function seeding_twice_does_not_duplicate_the_demo_orders(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(6, Order::count());
    }
}

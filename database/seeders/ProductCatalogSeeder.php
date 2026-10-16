<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Database\Seeder;

/**
 * The fixed catalogue from the assessment brief. Idempotent (safe to re-run).
 */
class ProductCatalogSeeder extends Seeder
{
    /** @var array<string, array{units_per_hour: int, products: list<string>}> */
    public const CATALOG = [
        'Type 1' => ['units_per_hour' => 715, 'products' => ['A', 'B', 'F']],
        'Type 2' => ['units_per_hour' => 770, 'products' => ['C']],
        'Type 3' => ['units_per_hour' => 1000, 'products' => ['D', 'E']],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $typeName => $definition) {
            $type = ProductType::updateOrCreate(
                ['name' => $typeName],
                ['units_per_hour' => $definition['units_per_hour']],
            );

            foreach ($definition['products'] as $productName) {
                Product::updateOrCreate(
                    ['name' => $productName],
                    ['product_type_id' => $type->id],
                );
            }
        }
    }
}

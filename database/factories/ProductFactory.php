<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Producto '.fake()->unique()->numerify('####'),
            'unit' => 'unidad',
            'purchase_price' => 10,
            'sale_price' => 20,
            'min_stock' => 0,
            'track_batches' => false,
            'active' => true,
        ];
    }

    public function batched(): static
    {
        return $this->state(fn () => ['track_batches' => true]);
    }
}

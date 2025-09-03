<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'product_name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 1, 1000),
            'stock' => $this->faker->numberBetween(0, 100),
            'promotion' => $this->faker->optional()->numberBetween(5, 50),
            'sale_start_date' => $this->faker->optional()->date(),
            'sale_end_date' => $this->faker->optional()->date(),
            'user_id' => User::factory(),
            'subcategory_id' => Subcategory::factory(),
            'type' => $this->faker->optional()->word(),
            'revenue' => $this->faker->optional()->randomFloat(2, 0, 10000),
            'content_status' => $this->faker->randomElement(['draft', 'published', 'archived']),
        ];
    }
}

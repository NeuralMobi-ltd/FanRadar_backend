<?php

namespace Database\Factories;

use App\Models\Subcategory;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubcategoryFactory extends Factory
{
    protected $model = Subcategory::class;

    public function definition()
    {
        return [
            'category_id' => Category::factory(),
            'name' => $this->faker->unique()->word(),
        ];
    }
}

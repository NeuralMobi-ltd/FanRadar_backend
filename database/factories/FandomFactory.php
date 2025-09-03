<?php

namespace Database\Factories;

use App\Models\Fandom;
use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class FandomFactory extends Factory
{
    protected $model = Fandom::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->word(),
            'description' => $this->faker->sentence(),
            'subcategory_id' => Subcategory::factory(),
        ];
    }
}

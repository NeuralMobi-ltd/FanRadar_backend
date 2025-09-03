<?php

namespace Database\Factories;

use App\Models\Rating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RatingFactory extends Factory
{
    protected $model = Rating::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'rateable_id' => 1, // à adapter selon le test
            'rateable_type' => 'App\\Models\\Post', // ou 'App\\Models\\Product'
            'evaluation' => $this->faker->numberBetween(0, 5),
            'commentaire' => $this->faker->optional()->sentence(),
        ];
    }
}

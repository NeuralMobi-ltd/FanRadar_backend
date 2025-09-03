<?php

namespace Database\Factories;

use App\Models\Favorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FavoriteFactory extends Factory
{
    protected $model = Favorite::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'favoriteable_id' => 1, // à adapter selon le test
            'favoriteable_type' => 'App\\Models\\Post', // ou 'App\\Models\\Product'
        ];
    }
}

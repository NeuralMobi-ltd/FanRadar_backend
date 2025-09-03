<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'feedback' => $this->faker->numberBetween(0, 100),
            'schedule_at' => $this->faker->optional()->dateTime(),
            'description' => $this->faker->sentence(),
            'content_status' => $this->faker->randomElement(['draft', 'published', 'archived']),
            'subcategory_id' => Subcategory::factory(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\User;
use App\Models\Fandom;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'fandom_id' => Fandom::factory(),
            'role' => $this->faker->randomElement(['admin', 'member', 'moderator']),
        ];
    }
}

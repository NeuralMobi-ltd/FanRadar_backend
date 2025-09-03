<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call([
            RolesTableSeeder::class,
            PermissionTableSeeder::class,
            AssignPermissionsToRolesSeeder::class,
            // ajoute ici tous tes seeders
        ]);

        \App\Models\Category::factory(5)->create();
        \App\Models\Subcategory::factory(10)->create();
        \App\Models\Product::factory(20)->create();
        \App\Models\Post::factory(20)->create();
        \App\Models\Tag::factory(10)->create();
        \App\Models\Favorite::factory(20)->create();
        \App\Models\Rating::factory(20)->create();
        \App\Models\Fandom::factory(5)->create();
        \App\Models\Member::factory(15)->create();
    }
}

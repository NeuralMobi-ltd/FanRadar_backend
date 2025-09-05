<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\DB;
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

        // Désactiver les contraintes de clés étrangères temporairement
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Vider les tables avant de créer de nouvelles données (dans l'ordre inverse des dépendances)
        \App\Models\Member::truncate();
        \App\Models\Favorite::truncate();
        \App\Models\Rating::truncate();
        DB::table('comments')->truncate(); // Vider la table comments en premier
        DB::table('taggables')->truncate(); // Vider la table pivot taggables
        \App\Models\Post::truncate();
        \App\Models\Product::truncate();
        \App\Models\Tag::truncate();
        \App\Models\Fandom::truncate();
        \App\Models\Subcategory::truncate();
        \App\Models\Category::truncate();
        \App\Models\User::truncate(); // Vider la table users

        // Réactiver les contraintes de clés étrangères
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Créer quelques utilisateurs avec des rôles assignés
        \App\Models\User::factory(10)->create();

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

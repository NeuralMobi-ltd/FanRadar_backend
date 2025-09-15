<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer le compte admin s'il n'existe pas déjà
        $admin = User::firstOrCreate(
            ['email' => 'admin@fanradar.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'FanRadar',
                'email' => 'admin@fanradar.com',
                'password' => Hash::make('123456789'),
                'profile_image' => null,
                'background_image' => null,
                'date_naissance' => null,
                'bio' => 'Compte administrateur principal',
                'gender' => null,
                'is_verified' => true,
                'email_verified_at' => now(),
            ]
        );

        // Assigner le rôle admin
        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        $this->command->info('Compte admin créé avec succès : admin@fanradar.com / 123456789');
    }
}

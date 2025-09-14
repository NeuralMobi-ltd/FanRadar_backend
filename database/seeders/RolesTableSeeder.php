<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * we have 3 roles: admin, user, and writer.
     */
    public function run(): void
    {
        $roles = ['admin', 'user', 'writer'];

        foreach ($roles as $role) {
            // Create roles for both web and sanctum guards
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'sanctum']);
        }

    }
}

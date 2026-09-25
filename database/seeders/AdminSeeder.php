<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // Create additional demo admin (same password as the primary admin).
        Admin::updateOrCreate(
            ['email' => 'demo@admin.com'],
            [
                'name' => 'Demo Admin',
                'email' => 'demo@admin.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
    }
}
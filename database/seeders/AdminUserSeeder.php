<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@meditative-brains.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);

        // Create a test user too
        User::create([
            'name' => 'Test User',
            'email' => 'user@meditative-brains.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);
    }
}

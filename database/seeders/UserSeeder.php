<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Main test user
        User::create([
            'name' => 'Ahmad Rahman',
            'email' => 'ahmad@example.com',
            'password' => Hash::make('password'),
        ]);

        // Secondary test user
        User::create([
            'name' => 'Sarah Lee',
            'email' => 'sarah@example.com',
            'password' => Hash::make('password'),
        ]);

        // Demo user
        User::create([
            'name' => 'Demo User',
            'email' => 'demo@bokku.app',
            'password' => Hash::make('demo1234'),
        ]);
    }
}
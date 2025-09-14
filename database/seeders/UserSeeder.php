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
        User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Ahmad Rahman',
                'password' => Hash::make('password'),
            ]
        );

        // Secondary test user
        User::firstOrCreate(
            ['email' => 'sarah@example.com'],
            [
                'name' => 'Sarah Lee',
                'password' => Hash::make('password'),
            ]
        );

        // Demo user
        User::firstOrCreate(
            ['email' => 'demo@bokku.app'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('demo1234'),
            ]
        );
    }
}

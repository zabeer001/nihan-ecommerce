<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's users table.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'binzabirtareq@gmail.com'],
            [
                'name' => 'Admin User',
                'role' => 'admin',
                'password' => Hash::make('jabirasgoeaskdkjfvndsvasd353@'),
            ]
        );

    }
}

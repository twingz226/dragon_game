<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Use firstOrCreate to prevent duplicates on re-deployment
        User::firstOrCreate(
            ['email' => 'krislan@gmail.com'],
            ['name' => 'Krislan', 'password' => 'krislan123']
        );

        User::firstOrCreate(
            ['email' => 'ranzel@gmail.com'],
            ['name' => 'Ranzel', 'password' => 'ranzel123']
        );

        User::firstOrCreate(
            ['email' => 'ryza@gmail.com'],
            ['name' => 'Ryza', 'password' => 'ryza123']
        );
    }
}

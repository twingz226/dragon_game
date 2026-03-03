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
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Krislan',
            'email' => 'krislan@gmail.com',
            'password' => 'krislan123',
        ]);

        User::factory()->create([
            'name' => 'Ranzel',
            'email' => 'ranzel@gmail.com',
            'password' => 'ranzel123',
        ]);

        User::factory()->create([
            'name' => 'Ryza',
            'email' => 'ryza@gmail.com',
            'password' => 'ryza123',
        ]);
    }
}

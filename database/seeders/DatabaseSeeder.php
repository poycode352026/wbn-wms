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
            'name' => 'Super Admin',
            'email' => 'superadmin@gmail.com',
            'password' => bcrypt('6260305001'),
            'role' => 'super_admin',
        ]);

        User::factory()->create([
            'name' => 'Bill Chen',
            'email' => 'billchen@gmail.com',
            'password' => bcrypt('bc12345'),
            'role' => 'warehouse_manager', 
        ]);

        User::factory()->create([
            'name' => 'User1',
            'email' => 'user1@gmail.com',
            'password' => bcrypt('12345'),
            'role' => 'operator', 
        ]);
    }
}

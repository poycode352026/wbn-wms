<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Super admin — no department
        User::create([
            'employee_id'       => '6260305001',
            'name'              => 'Super Admin',
            'email'             => 'superadmin@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'super_admin',
            'is_active'         => true,
            'department_id'     => null,
        ]);

        $this->call([
            WarehouseAndCategorySeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}

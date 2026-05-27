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
        $department = Department::create([
            'code' => 'IT',
            'name' => 'Information Technology',
        ]);

        User::create([
            'employee_id'       => '6260305001',
            'name'              => 'Super Admin',
            'email'             => 'superadmin@wbn.co.id',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'super_admin',
            'is_active'         => true,
            'department_id'     => $department->id,
        ]);

        User::create([
            'employee_id'       => '6260305002',
            'name'              => 'Dea',
            'email'             => 'dea@wbn.co.id',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'admin_dept',
            'is_active'         => true,
            'department_id'     => $department->id,
        ]);

        $this->call(WarehouseAndCategorySeeder::class);
    }
}

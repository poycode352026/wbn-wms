<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Employee;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\WarehouseAndCategorySeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::create([
            'code' => 'IT',
            'name' => 'Information Technology',
        ]);

        Employee::create([
            'employee_id'   => '6260305001',
            'name'          => 'Super Admin',
            'department_id' => $department->id,
            'position'      => 'Super Admin',
            'is_active'     => true,
        ]);

        Employee::create([
            'employee_id'   => '6260305002',
            'name'          => 'Dea',
            'department_id' => $department->id,
            'position'      => 'Admin Dept',
            'is_active'     => true,
        ]);

        User::create([
            'employee_id'        => '6260305001',
            'name'               => 'Super Admin',
            'email'              => 'superadmin@wbn.co.id',
            'email_verified_at'  => now(),
            'password'           => Hash::make('password'),
            'role'               => 'super_admin',
            'is_active'          => true,
            'department_id'      => $department->id,
        ]);

        User::create([
            'employee_id'        => '6260305002',
            'name'               => 'Dea',
            'email'              => 'dea@wbn.co.id',
            'email_verified_at'  => now(),
            'password'           => Hash::make('password'),
            'role'               => 'admin_dept',
            'is_active'          => true,
            'department_id'      => $department->id,
        ]);

        $this->call(WarehouseAndCategorySeeder::class);
    }
}
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::create([
            'name'        => 'Super Admin',
            'employee_id' => '6260305001',
            'email'       => 'superadmin@wbn.co.id',
            'password'    => Hash::make('password'),
            'role'        => 'super_admin',
            'is_active'   => true,
        ]);

        User::create([
            'name'        => 'Dea',
            'employee_id' => '6260305002',
            'email'       => 'dea@wbn.co.id',
            'password'    => Hash::make('password'),
            'role'        => 'admin_dept',
            'is_active'   => true,
        ]);
    }
}
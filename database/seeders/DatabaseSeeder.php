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
        // ── Departments ────────────────────────────────────────────────────────
        // $deptIT = Department::create([
        //     'name' => 'Departemen IT',
        //     'code' => 'IT',
        // ]);
        // $deptMaint = Department::create([
        //     'name' => 'Departemen Maintenance',
        //     'code' => 'MNT',
        // ]);

        // ── Super Admin ────────────────────────────────────────────────────────
        User::create([
            'employee_id'       => '6260305001',
            'name'              => 'Super Admin',
            'email'             => 'superadmin@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('superadmin'),
            'role'              => 'super_admin',
            'is_active'         => true,
        ]);

        // ── Procurement Admin ───────────────────────────────────────────────────
        User::create([
            'employee_id'       => '6250417003',
            'name'              => 'Alvin Anderson',
            'email'             => 'alvin.anderson@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('6250417003'),
            'role'              => 'procurement_admin',
            'is_active'         => true,
        ]);

        // ── WH Admin ───────────────────────────────────────────────────────────
        User::create([
            'employee_id'       => '6230923009',
            'name'              => 'Ari Wibowo',
            'email'             => 'ari.wibowo@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('6230923009'),
            'role'              => 'wh_admin',
            'is_active'         => true,
        ]);

        // ── WH Supervisor ──────────────────────────────────────────────────────
        User::create([
            'employee_id'       => '6260305002',
            'name'              => 'Arseno',
            'email'             => 'arsenor@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('6260305002'),
            'role'              => 'wh_supervisor',
            'is_active'         => true,
        ]);

        // ── WH Manager ─────────────────────────────────────────────────────────
        User::create([
            'employee_id'       => '6260305003',
            'name'              => 'Bill Chen',
            'email'             => 'bill.chen@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('6260305003'),
            'role'              => 'wh_manager',
            'is_active'         => true,
        ]);

        // ── Admin Dept  ────────────────────────────────────────────────────
        User::create([
            'employee_id'       => '11111111',
            'name'              => 'Frediyodi',
            'email'             => 'admindept@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'admin_dept',
            'department_id'     =>'user',
            'is_active'         => true,
        ]);

        // ── Manager Dept ──────────────────────────────────────────────────
        User::create([
            'employee_id'       => '22222222',
            'name'              => 'Salim Lim',
            'email'             => 'salim.lim@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('22222222'),
            'role'              => 'manager_dept',
            'department_id'     => 'user',
            'is_active'         => true,
        ]);

        $this->call([
            WarehouseAndCategorySeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}

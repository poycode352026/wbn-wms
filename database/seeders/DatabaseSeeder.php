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
            'password'          => Hash::make('password'),
            'role'              => 'super_admin',
            'is_active'         => true,
        ]);

        // ── Procurement Admin ───────────────────────────────────────────────────
        User::create([
            'employee_id'       => '6260305002',
            'name'              => 'Admin Procurement',
            'email'             => 'adminpr@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'procurement_admin',
            'is_active'         => true,
        ]);

        // ── WH Admin ───────────────────────────────────────────────────────────
        User::create([
            'employee_id'       => '6260305003',
            'name'              => 'Admin Gudang',
            'email'             => 'whadmin@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'wh_admin',
            'is_active'         => true,
        ]);

        // ── WH Supervisor ──────────────────────────────────────────────────────
        User::create([
            'employee_id'       => '6260305004',
            'name'              => 'Supervisor Gudang',
            'email'             => 'supervisor@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'wh_supervisor',
            'is_active'         => true,
        ]);

        // ── WH Manager ─────────────────────────────────────────────────────────
        User::create([
            'employee_id'       => '6260305005',
            'name'              => 'Manager Gudang',
            'email'             => 'whmanager@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('password'),
            'role'              => 'wh_manager',
            'is_active'         => true,
        ]);

        // // ── Admin Dept (IT) ────────────────────────────────────────────────────
        // User::create([
        //     'employee_id'       => '6260305006',
        //     'name'              => 'Admin Dept IT',
        //     'email'             => 'admindept@gmail.com',
        //     'email_verified_at' => now(),
        //     'password'          => Hash::make('password'),
        //     'role'              => 'admin_dept',
        //     'department_id'     => $deptIT->id,
        //     'is_active'         => true,
        // ]);

        // // ── Manager Dept (IT) ──────────────────────────────────────────────────
        // User::create([
        //     'employee_id'       => '6260305007',
        //     'name'              => 'Manager Dept IT',
        //     'email'             => 'managerdept@gmail.com',
        //     'email_verified_at' => now(),
        //     'password'          => Hash::make('password'),
        //     'role'              => 'manager_dept',
        //     'department_id'     => $deptIT->id,
        //     'is_active'         => true,
        // ]);

        $this->call([
            WarehouseAndCategorySeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}

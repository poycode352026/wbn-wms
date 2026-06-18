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
            'employee_id'       => '00000000',
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
            'password'          => Hash::make('7003@wbn.co.id'),
            'role'              => 'procurement_admin',
            'is_active'         => true,
        ]);

        // ── WH Admin ───────────────────────────────────────────────────────────
        User::create([
            'employee_id'       => '6230923009',
            'name'              => 'Ari Wibowo',
            'email'             => 'ari.wibowo@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('3009@wbn.co.id'),
            'role'              => 'wh_admin',
            'is_active'         => true,
        ]);
            User::create([
            'employee_id'       => '6260305001',
            'name'              => 'David',
            'email'             => 'david@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('5001@wbn.co.id'),
            'role'              => 'wh_admin',
            'is_active'         => true,
        ]);

        // ── WH Supervisor ──────────────────────────────────────────────────────
        User::create([
            'employee_id'       => '22307066',
            'name'              => 'Arseno',
            'email'             => 'arsenor@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('7066@wbn.co.id'),
            'role'              => 'wh_supervisor',
            'is_active'         => true,
        ]);

        // ── WH Manager ─────────────────────────────────────────────────────────
        User::create([
            'employee_id'       => '6260305003',
            'name'              => 'Bill Chen',
            'email'             => 'bill.chen@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('5003@wbn.co.id'),
            'role'              => 'wh_manager',
            'is_active'         => true,
        ]);
        // ── WH Operator ─────────────────────────────────────────────────────────
        User::create([
            'employee_id'       => '8241017068',
            'name'              => 'Try Fadhil Muhamad',
            'email'             => 'try.fadhil.muhamad@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('7068@wbn.co.id'),
            'role'              => 'operator',
            'is_active'         => true,
        ]);
        User::create([
            'employee_id'       => '8241014045',
            'name'              => 'Bastian',
            'email'             => 'bastian@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('4045@wbn.co.id'),
            'role'              => 'operator',
            'is_active'         => true,
        ]);
        User::create([
            'employee_id'       => '8241018053',
            'name'              => 'Bernat Yosias Hidupa',
            'email'             => 'bernat.yosias.hidupa@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('8053@wbn.co.id'),
            'role'              => 'operator',
            'is_active'         => true,
        ]);
        User::create([
            'employee_id'       => '8241017058',
            'name'              => 'Apriansyah.Poluan',
            'email'             => 'apriansyah.poluan@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('7058@wbn.co.id'),
            'role'              => 'operator',
            'is_active'         => true,
        ]);


        // ── Admin Dept  ────────────────────────────────────────────────────
        User::create([
            'employee_id'       => '6230302012',
            'name'              => 'Frediyodi',
            'email'             => 'admindept@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('2012@wbn.co.id'),
            'role'              => 'admin_dept',
            'is_active'         => true,
        ]);

        // ── Manager Dept ──────────────────────────────────────────────────
        User::create([
            'employee_id'       => '11111111',
            'name'              => 'Salim Lim',
            'email'             => 'salim.lim@gmail.com',
            'email_verified_at' => now(),
            'password'          => Hash::make('1111@wbn.co.id'),
            'role'              => 'manager_dept',
            'is_active'         => true,
        ]);

        $this->call([
            WarehouseAndCategorySeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}

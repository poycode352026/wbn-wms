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
        User::firstOrCreate(['email' => 'superadmin@gmail.com'], [
            'employee_id'       => '00000000',
            'name'              => 'Super Admin',
            'email_verified_at' => now(),
            'password'          => Hash::make('superadmin'),
            'role'              => 'super_admin',
            'is_active'         => true,
        ]);

        // ── Procurement Admin ───────────────────────────────────────────────────
        User::firstOrCreate(['email' => 'alvin.anderson@gmail.com'], [
            'employee_id'       => '6250417003',
            'name'              => 'Alvin Anderson',
            'email_verified_at' => now(),
            'password'          => Hash::make('7003@wbn.co.id'),
            'role'              => 'procurement_admin',
            'is_active'         => true,
        ]);

        // ── WH Admin ───────────────────────────────────────────────────────────
        User::firstOrCreate(['email' => 'ari.wibowo@gmail.com'], [
            'employee_id'       => '6230923009',
            'name'              => 'Ari Wibowo',
            'email_verified_at' => now(),
            'password'          => Hash::make('3009@wbn.co.id'),
            'role'              => 'wh_admin',
            'is_active'         => true,
        ]);
        User::firstOrCreate(['email' => 'david@gmail.com'], [
            'employee_id'       => '6260305001',
            'name'              => 'David',
            'email_verified_at' => now(),
            'password'          => Hash::make('5001@wbn.co.id'),
            'role'              => 'wh_admin',
            'is_active'         => true,
        ]);

        // ── WH Supervisor ──────────────────────────────────────────────────────
        User::firstOrCreate(['email' => 'arsenor@gmail.com'], [
            'employee_id'       => '22307066',
            'name'              => 'Arseno',
            'email_verified_at' => now(),
            'password'          => Hash::make('7066@wbn.co.id'),
            'role'              => 'wh_supervisor',
            'is_active'         => true,
        ]);

        // ── WH Manager ─────────────────────────────────────────────────────────
        User::firstOrCreate(['email' => 'bill.chen@gmail.com'], [
            'employee_id'       => '660006618',
            'name'              => 'Bill Chen',
            'email_verified_at' => now(),
            'password'          => Hash::make('6618@wbn.co.id'),
            'role'              => 'wh_manager',
            'is_active'         => true,
        ]);

        // ── WH Operator ────────────────────────────────────────────────────────
        User::firstOrCreate(['email' => 'try.fadhil.muhamad@gmail.com'], [
            'employee_id'       => '8241017068',
            'name'              => 'Try Fadhil Muhamad',
            'email_verified_at' => now(),
            'password'          => Hash::make('7068@wbn.co.id'),
            'role'              => 'operator',
            'is_active'         => true,
        ]);
        User::firstOrCreate(['email' => 'bastian@gmail.com'], [
            'employee_id'       => '8241014045',
            'name'              => 'Bastian',
            'email_verified_at' => now(),
            'password'          => Hash::make('4045@wbn.co.id'),
            'role'              => 'operator',
            'is_active'         => true,
        ]);
        User::firstOrCreate(['email' => 'bernat.yosias.hidupa@gmail.com'], [
            'employee_id'       => '8241018053',
            'name'              => 'Bernat Yosias Hidupa',
            'email_verified_at' => now(),
            'password'          => Hash::make('8053@wbn.co.id'),
            'role'              => 'operator',
            'is_active'         => true,
        ]);
        User::firstOrCreate(['email' => 'apriansyah.poluan@gmail.com'], [
            'employee_id'       => '8241017058',
            'name'              => 'Apriansyah.Poluan',
            'email_verified_at' => now(),
            'password'          => Hash::make('7058@wbn.co.id'),
            'role'              => 'operator',
            'is_active'         => true,
        ]);

        // ── Admin Dept ─────────────────────────────────────────────────────────
        User::firstOrCreate(['email' => 'admindept@gmail.com'], [
            'employee_id'       => '6230302012',
            'name'              => 'Frediyodi',
            'email_verified_at' => now(),
            'password'          => Hash::make('2012@wbn.co.id'),
            'role'              => 'admin_dept',
            'is_active'         => true,
        ]);

        // ── Manager Dept ───────────────────────────────────────────────────────
        User::firstOrCreate(['email' => 'salim.lim@gmail.com'], [
            'employee_id'       => '21900781',
            'name'              => 'Salim Lim',
            'email_verified_at' => now(),
            'password'          => Hash::make('0781@wbn.co.id'),
            'role'              => 'manager_dept',
            'is_active'         => true,
        ]);

        $this->call([
            DepartmentSeeder::class,
            WarehouseAndCategorySeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}
